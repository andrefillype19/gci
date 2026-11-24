import http from "k6/http";
import { check, sleep } from "k6";
import { Counter, Trend } from "k6/metrics";

// Teste de CONCORRENCIA sobre o MESMO recurso (complementa loadtest.js, em que cada usuario
// virtual usa sua propria sessao e seus proprios arquivos, sem disputa).
//
// Dois cenarios rodam ao mesmo tempo:
//
//  1. sessao_compartilhada - cada usuario virtual faz login uma vez e dispara SESSION_PARALLEL
//     requisicoes simultaneas (http.batch) com o MESMO cookie de sessao em user/profile.
//     O PHP trava o arquivo de sessao enquanto a requisicao esta em andamento e o regrava ao
//     final, entao as requisicoes disputam o mesmo arquivo (leitura + escrita + bloqueio).
//     - SESSION_AFFINITY=keep (padrao): o cookie GCI_ROUTE e enviado, e as requisicoes da mesma
//       sessao caem no mesmo pod (comportamento real do roteamento sticky).
//     - SESSION_AFFINITY=drop: apenas o PHPSESSID e enviado, sem o cookie de afinidade; o ingress
//       distribui as requisicoes da mesma sessao entre as replicas, e a disputa passa a ocorrer
//       entre pods diferentes sobre o mesmo arquivo no NFS.
//
//  2. arquivo_compartilhado - um unico subevento (criado no setup) tem um PDF com nome fixo.
//     READERS usuarios virtuais emitem o certificado (atividade/certpal, que le o PDF) em laco,
//     enquanto WRITERS usuarios virtuais regravam o MESMO PDF (subevento/update, que apaga o
//     arquivo anterior e grava o novo no mesmo caminho) e atualizam a mesma linha do banco.
//     Uma leitura que coincide com a janela entre apagar e gravar encontra o arquivo ausente ou
//     incompleto (HTTP 500 ou conteudo invalido).
//
// Variaveis: BASE_URL (obrigatoria), DURATION (5m), SESSION_VUS (10), SESSION_PARALLEL (6),
// SESSION_AFFINITY (keep|drop), READERS (20), WRITERS (4), WRITE_PAUSE_MS (100), SMOKE=1.

const BASE_URL = (__ENV.BASE_URL || "").replace(/\/+$/, "");
if (!BASE_URL) {
  throw new Error("Defina BASE_URL, por exemplo: $env:BASE_URL = 'http://gci.local'");
}
const LOGIN_URL = `${BASE_URL}/index.php?r=user/login`;
const PROFILE_URL = `${BASE_URL}/index.php?r=user/profile`;
const EVENT_CREATE_URL = `${BASE_URL}/index.php?r=evento/create`;
const SUBEVENTO_CREATE_URL = `${BASE_URL}/index.php?r=subevento/create`;
const SUBEVENTO_UPDATE_URL = (id) => `${BASE_URL}/index.php?r=subevento/update&id=${id}`;
const ATIVIDADE_CREATE_URL = `${BASE_URL}/index.php?r=atividade/create`;
const CERTPAL_URL = (id) => `${BASE_URL}/index.php?r=atividade/certpal&id=${id}&mode=1`;

const USERNAME = "GCI";
const PASSWORD = "admin";
const UPLOAD_FILE = open("./certificado-test-k6.pdf", "b");

// Os prefixos e o nome do arquivo seguem o padrao que reset-test-data.ps1 ja remove.
const EVENT_TITLE_PREFIX = "k6-loadtest";
const SUBEVENTO_NAME_PREFIX = "cert-k6";
const SHARED_FILE_NAME = "certificado-test-k6-compartilhado.pdf";
const SUBEVENTO_TYPE_CURSOS = "11";
const TEST_DATE = "01/10/2026";

const SMOKE = __ENV.SMOKE === "1";
const DURATION = __ENV.DURATION || (SMOKE ? "20s" : "5m");
const SESSION_VUS = Number(__ENV.SESSION_VUS || (SMOKE ? 2 : 10));
const SESSION_PARALLEL = Number(__ENV.SESSION_PARALLEL || 6);
const SESSION_AFFINITY = (__ENV.SESSION_AFFINITY || "keep").toLowerCase();
const READERS = Number(__ENV.READERS || (SMOKE ? 3 : 20));
const WRITERS = Number(__ENV.WRITERS || (SMOKE ? 1 : 4));
const WRITE_PAUSE_MS = Number(__ENV.WRITE_PAUSE_MS || 100);

export const sessionOk = new Counter("conc_session_ok");
export const sessionFail = new Counter("conc_session_fail");
export const fileReadOk = new Counter("conc_file_read_ok");
export const fileReadFail = new Counter("conc_file_read_fail");
export const fileWriteOk = new Counter("conc_file_write_ok");
export const fileWriteFail = new Counter("conc_file_write_fail");
export const sessionBatchMs = new Trend("conc_session_batch_ms", true);
export const fileReadMs = new Trend("conc_file_read_ms", true);
export const fileWriteMs = new Trend("conc_file_write_ms", true);

// Um cenario so e criado quando o numero de usuarios virtuais e maior que zero
// (WRITERS=0 faz um controle sem regravacao do arquivo).
const scenarios = {};
if (SESSION_VUS > 0) {
  scenarios.sessao_compartilhada = {
    executor: "constant-vus",
    vus: SESSION_VUS,
    duration: DURATION,
    exec: "sessionContention",
    tags: { flow: "conc_sessao" },
  };
}
if (READERS > 0) {
  scenarios.arquivo_leitores = {
    executor: "constant-vus",
    vus: READERS,
    duration: DURATION,
    exec: "fileReader",
    tags: { flow: "conc_arquivo", role: "leitor" },
  };
}
if (WRITERS > 0) {
  scenarios.arquivo_escritores = {
    executor: "constant-vus",
    vus: WRITERS,
    duration: DURATION,
    exec: "fileWriter",
    tags: { flow: "conc_arquivo", role: "escritor" },
  };
}

export const options = {
  // Sem isso o k6 limita a 6 requisicoes simultaneas por host dentro de um http.batch.
  batchPerHost: Math.max(SESSION_PARALLEL, 6),
  scenarios,
};

function params(operation, jar) {
  const p = { redirects: 0, headers: { Connection: "close" }, tags: { operation } };
  if (jar) p.jar = jar;
  return p;
}

function locationOf(response) {
  return response.headers.Location || response.headers.location || "";
}

function idFromRedirect(response) {
  const match = locationOf(response).match(/[?&]id=(\d+)/);
  return match ? match[1] : null;
}

function login(jar) {
  http.get(LOGIN_URL, params("login-page", jar));
  const response = http.post(
    LOGIN_URL,
    { "UserLogin[username]": USERNAME, "UserLogin[password]": PASSWORD },
    params("login", jar)
  );
  const hasCookie = Object.keys(jar.cookiesForURL(BASE_URL)).length > 0;
  return response.status >= 300 && response.status < 400 && hasCookie;
}

function newSession() {
  const jar = http.cookieJar();
  jar.clear(BASE_URL);
  return login(jar) ? jar : null;
}

function sessionId(jar) {
  const values = jar.cookiesForURL(BASE_URL)["PHPSESSID"];
  return values && values.length ? values[0] : null;
}

// O Prometheus so cria a serie de um Counter quando ele e incrementado; emitir 0 para cada motivo
// conhecido faz as series existirem desde o inicio (mesma tecnica de loadtest.js).
let initialized = false;
function initCounters() {
  if (initialized) return;
  initialized = true;
  ["session_lost", "http_500", "unexpected_status", "login_failed"].forEach((reason) =>
    sessionFail.add(0, { flow: "conc_sessao", reason }));
  ["session_lost", "http_500", "invalid_content", "unexpected_status", "login_failed"].forEach((reason) =>
    fileReadFail.add(0, { flow: "conc_arquivo", reason }));
  ["update_failed", "login_failed"].forEach((reason) =>
    fileWriteFail.add(0, { flow: "conc_arquivo", reason }));
}

// ---------------------------------------------------------------- preparacao do recurso compartilhado
function uploadPayload(fields) {
  return Object.assign({}, fields, {
    "Subevento[filepath]": http.file(UPLOAD_FILE, SHARED_FILE_NAME, "application/pdf"),
  });
}

function subeventoFields(eventId, name) {
  return {
    "Subevento[id_evento]": eventId,
    "Subevento[tipo]": SUBEVENTO_TYPE_CURSOS,
    "Subevento[nome]": name,
    "Subevento[generomasculino]": "0",
  };
}

export function setup() {
  const jar = http.cookieJar();
  jar.clear(BASE_URL);
  if (!login(jar)) throw new Error("setup: login falhou (confira BASE_URL e o usuario de teste)");

  const stamp = Date.now();

  const event = http.post(
    EVENT_CREATE_URL,
    {
      "Evento[tituloevento]": `${EVENT_TITLE_PREFIX}-compartilhado-${stamp}`,
      "Evento[local]": "Grafana",
      "Evento[instituicaopromotora]": "k6",
      "Evento[cidade]": "Anápolis",
      "Evento[datainicial]": TEST_DATE,
      "Evento[datafinal]": "01/11/2026",
      "Evento[datainscricaoi]": TEST_DATE,
      "Evento[datainscricaof]": "15/10/2026",
      "Evento[printcertificate]": "0",
    },
    params("setup-event", jar)
  );
  const eventId = idFromRedirect(event);
  if (event.status !== 302 || !eventId) throw new Error(`setup: evento nao criado (HTTP ${event.status})`);

  const subName = `${SUBEVENTO_NAME_PREFIX}-compartilhado-${stamp}`;
  const sub = http.post(SUBEVENTO_CREATE_URL, uploadPayload(subeventoFields(eventId, subName)), params("setup-subevento", jar));
  const subId = idFromRedirect(sub);
  if (sub.status !== 302 || !subId) throw new Error(`setup: subevento nao criado (HTTP ${sub.status})`);

  const act = http.post(
    ATIVIDADE_CREATE_URL,
    {
      "Atividade[tema]": `${EVENT_TITLE_PREFIX}-compartilhado-${stamp}`,
      "Atividade[qtdhoras]": "1",
      "Atividade[vagasdisponiveis]": "1",
      "Atividade[id_subevento]": subId,
      "Atividade[presenca]": "0",
      "Atividade[id_evento]": eventId,
      "Atividade[data]": TEST_DATE,
      "Atividade[horainicial]": "10:00:00",
      "Atividade[horafinal]": "11:00:00",
      "Atividade[Proponente]": "k6",
      "Atividade[tipo]": "Cursos e minicursos",
      "Atividade[eletivo]": "0",
      "Atividade[multihorario]": "0",
    },
    params("setup-atividade", jar)
  );
  const actId = idFromRedirect(act);
  if (act.status !== 302 || !actId) throw new Error(`setup: atividade nao criada (HTTP ${act.status})`);

  const probe = http.get(CERTPAL_URL(actId), params("setup-certpal", jar));
  const ctype = (probe.headers["Content-Type"] || probe.headers["content-type"] || "").toLowerCase();
  if (probe.status !== 200 || !ctype.includes("application/pdf")) {
    throw new Error(`setup: certpal nao devolveu o PDF (HTTP ${probe.status})`);
  }

  console.log(`recurso compartilhado: evento=${eventId} subevento=${subId} atividade=${actId}`);
  return { eventId, subId, actId, subName };
}

// ---------------------------------------------------------------- cenario 1: sessao compartilhada
let sessionJar = null;

function profileRequest(jar, sid) {
  if (SESSION_AFFINITY === "drop" && sid) {
    // Somente o PHPSESSID, em um jar vazio: sem o cookie GCI_ROUTE o ingress escolhe o pod a cada requisicao.
    return ["GET", PROFILE_URL, null, {
      redirects: 0,
      jar: new http.CookieJar(),
      headers: { Connection: "close", Cookie: `PHPSESSID=${sid}` },
      tags: { operation: "profile-concorrente" },
    }];
  }
  return ["GET", PROFILE_URL, null, params("profile-concorrente", jar)];
}

export function sessionContention() {
  initCounters();
  if (!sessionJar) {
    sessionJar = newSession();
    if (!sessionJar) {
      sessionFail.add(1, { flow: "conc_sessao", reason: "login_failed" });
      sleep(1);
      return;
    }
  }

  const sid = sessionId(sessionJar);
  const requests = [];
  for (let i = 0; i < SESSION_PARALLEL; i++) requests.push(profileRequest(sessionJar, sid));

  const started = Date.now();
  const responses = http.batch(requests);
  sessionBatchMs.add(Date.now() - started, { flow: "conc_sessao", affinity: SESSION_AFFINITY });

  let lost = false;
  responses.forEach((r) => {
    const location = locationOf(r);
    if (r.status === 200 && !location.includes("user/login")) {
      sessionOk.add(1, { flow: "conc_sessao" });
    } else {
      let reason = "unexpected_status";
      if (r.status === 500) reason = "http_500";
      else if (r.status >= 300 && r.status < 400 && location.includes("user/login")) {
        reason = "session_lost";
        lost = true;
      }
      sessionFail.add(1, { flow: "conc_sessao", reason });
    }
  });
  check(responses, { "todas as requisicoes concorrentes mantem a sessao": (rs) => rs.every((r) => r.status === 200) });

  if (lost) sessionJar = null; // sessao perdida: o proximo ciclo faz novo login
  sleep(0.2);
}

// ---------------------------------------------------------------- cenario 2: arquivo compartilhado
let readerJar = null;
let writerJar = null;

const PDF_MAGIC = [0x25, 0x50, 0x44, 0x46]; // "%PDF"

function isPdf(response) {
  const ctype = (response.headers["Content-Type"] || response.headers["content-type"] || "").toLowerCase();
  if (response.status !== 200 || !ctype.includes("application/pdf")) return false;
  const body = response.body;
  if (!body || body.byteLength < 1000) return false;
  const head = new Uint8Array(body, 0, 4);
  return PDF_MAGIC.every((b, i) => head[i] === b);
}

export function fileReader(data) {
  initCounters();
  if (!readerJar) {
    readerJar = newSession();
    if (!readerJar) {
      fileReadFail.add(1, { flow: "conc_arquivo", reason: "login_failed" });
      sleep(1);
      return;
    }
  }

  const p = params("certpal-compartilhado", readerJar);
  p.responseType = "binary";
  const started = Date.now();
  const response = http.get(CERTPAL_URL(data.actId), p);
  fileReadMs.add(Date.now() - started, { flow: "conc_arquivo" });

  if (isPdf(response)) {
    fileReadOk.add(1, { flow: "conc_arquivo" });
  } else {
    let reason = "unexpected_status";
    const location = locationOf(response);
    if (response.status === 500) reason = "http_500";
    else if (response.status >= 300 && response.status < 400 && location.includes("user/login")) {
      reason = "session_lost";
      readerJar = null;
    } else if (response.status === 200) reason = "invalid_content";
    fileReadFail.add(1, { flow: "conc_arquivo", reason });
  }
  check(response, { "leitura concorrente devolve PDF valido": () => isPdf(response) });
}

export function fileWriter(data) {
  initCounters();
  if (!writerJar) {
    writerJar = newSession();
    if (!writerJar) {
      fileWriteFail.add(1, { flow: "conc_arquivo", reason: "login_failed" });
      sleep(1);
      return;
    }
  }

  const started = Date.now();
  const response = http.post(
    SUBEVENTO_UPDATE_URL(data.subId),
    uploadPayload(subeventoFields(data.eventId, `${data.subName}`)),
    params("subevento-update-compartilhado", writerJar)
  );
  fileWriteMs.add(Date.now() - started, { flow: "conc_arquivo" });

  const ok = response.status === 302 && locationOf(response).includes("subevento");
  if (ok) {
    fileWriteOk.add(1, { flow: "conc_arquivo" });
  } else {
    if (response.status >= 300 && response.status < 400 && locationOf(response).includes("user/login")) writerJar = null;
    fileWriteFail.add(1, { flow: "conc_arquivo", reason: "update_failed" });
  }
  check(response, { "regravacao do PDF compartilhado concluida": () => ok });
  sleep(WRITE_PAUSE_MS / 1000);
}
