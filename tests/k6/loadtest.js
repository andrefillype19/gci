import http from "k6/http";
import { check, group, sleep } from "k6";
import { Counter } from "k6/metrics";

// URL base da aplicacao na AWS (IP publico da EC2 da aplicacao, porta 80).
// Exemplo: BASE_URL=http://3.140.10.20  (sem barra no final)
const BASE_URL = (__ENV.BASE_URL || "").replace(/\/+$/, "");
if (!BASE_URL) {
  throw new Error("Defina BASE_URL, por exemplo: $env:BASE_URL = 'http://<ip-publico-da-ec2>'");
}
const LOGIN_URL = `${BASE_URL}/index.php?r=user/login`;
const PROFILE_URL = `${BASE_URL}/index.php?r=user/profile`;
const EVENT_CREATE_URL = `${BASE_URL}/index.php?r=evento/create`;
const SUBEVENTO_CREATE_URL = `${BASE_URL}/index.php?r=subevento/create`;
const ATIVIDADE_CREATE_URL = `${BASE_URL}/index.php?r=atividade/create`;
const CERTPAL_URL = (id) => `${BASE_URL}/index.php?r=atividade/certpal&id=${id}&mode=1`;

const USERNAME = "GCI";
const PASSWORD = "admin";
const UPLOAD_FILE = open("./certificado-test-k6.pdf", "b");

const EVENT_TITLE_PREFIX = "k6-loadtest";
const SUBEVENTO_NAME_PREFIX = "cert-k6";
const SUBEVENTO_TYPE_CURSOS = "11";
const TEST_DATE = "01/10/2026";

export const sessionSuccess = new Counter("session_success");
export const sessionLoss = new Counter("session_loss");
export const uploadSuccess = new Counter("upload_success");
export const uploadFailure = new Counter("upload_failure");
export const fileVerifySuccess = new Counter("file_verify_success");
export const fileVerifyFailure = new Counter("file_verify_failure");

const VUS_SESSION = Number(__ENV.VUS_SESSION || 30);
const VUS_UPLOAD = Number(__ENV.VUS_UPLOAD || 15);

// PROFILE:
//   steady (padrao) - 2m subida, 10m platao, 2m descida (comparar os 4 cenarios)
//   hpa             - subida, pico, queda e periodo de baixa carga para o HPA
//                     voltar a 1 replica (ver variaveis HPA_* abaixo)
//   smoke           - uma iteracao de cada fluxo (tambem aceita SMOKE=1)
const PROFILE = __ENV.SMOKE === "1" ? "smoke" : (__ENV.PROFILE || "steady");
const SMOKE = PROFILE === "smoke";

// Perfil "hpa": duracoes e fracao da carga mantida durante o cooldown.
const HPA_RAMP_UP = __ENV.HPA_RAMP_UP || "3m";
const HPA_PEAK = __ENV.HPA_PEAK || "6m";
const HPA_RAMP_DOWN = __ENV.HPA_RAMP_DOWN || "2m";
const HPA_COOLDOWN = __ENV.HPA_COOLDOWN || "6m";
const HPA_LOW_FRACTION = Number(__ENV.HPA_LOW_FRACTION || 0.1);

function createStages(targetVUs) {
  if (PROFILE === "hpa") {
    // Carga baixa continua no cooldown: mede sessoes/arquivos depois que o HPA
    // remove replicas, sem manter CPU acima do alvo.
    // HPA_LOW_FRACTION=0 desce direto a 0 VUs e dispensa o cooldown (teste curto de validacao).
    const low = HPA_LOW_FRACTION === 0 ? 0 : Math.max(1, Math.round(targetVUs * HPA_LOW_FRACTION));
    const isZero = (d) => /^0+[smh]?$/.test(d);
    const stages = [
      { duration: HPA_RAMP_UP, target: targetVUs },
      { duration: HPA_PEAK, target: targetVUs },
      { duration: HPA_RAMP_DOWN, target: low },
    ];
    if (low > 0) {
      stages.push({ duration: HPA_COOLDOWN, target: low });
      stages.push({ duration: "30s", target: 0 });
    }
    return stages.filter((s) => !isZero(s.duration));
  }
  return [
    { duration: "2m", target: targetVUs },
    { duration: "10m", target: targetVUs },
    { duration: "2m", target: 0 },
  ];
}

export const options = {
  scenarios: SMOKE
    ? {
        smoke: {
          executor: "per-vu-iterations",
          vus: 1,
          iterations: 1,
          exec: "smokeTest",
        },
      }
    : {
        session: {
          executor: "ramping-vus",
          startVUs: 0,
          stages: createStages(VUS_SESSION),
          exec: "sessionTest",
          tags: { flow: "session" },
          gracefulRampDown: "30s",
        },
        upload: {
          executor: "ramping-vus",
          startVUs: 0,
          stages: createStages(VUS_UPLOAD),
          exec: "uploadTest",
          tags: { flow: "upload" },
          gracefulRampDown: "30s",
        },
      },
};

function requestParams(flow, operation, jar) {
  const params = {
    redirects: 0,
    headers: { Connection: "close" },
    tags: { flow, operation },
  };
  if (jar) {
    params.jar = jar;
  }
  return params;
}

function locationOf(response) {
  return response.headers.Location || response.headers.location || "";
}

function idFromRedirect(response) {
  const match = locationOf(response).match(/[?&]id=(\d+)/);
  return match ? match[1] : null;
}

function login(jar, flow) {
  const page = http.get(LOGIN_URL, requestParams(flow, "login-page", jar));
  check(page, { "login page 200": (r) => r.status === 200 }, { flow });

  const response = http.post(
    LOGIN_URL,
    {
      "UserLogin[username]": USERNAME,
      "UserLogin[password]": PASSWORD,
    },
    requestParams(flow, "login", jar)
  );

  const hasCookie = Object.keys(jar.cookiesForURL(BASE_URL)).length > 0;
  const isRedirect = response.status >= 300 && response.status < 400;
  const ok = isRedirect && hasCookie;

  check(response, {
    "login redirects after auth": () => isRedirect,
    "session cookie emitted": () => hasCookie,
  }, { flow });

  return ok;
}

function createEvent(jar, suffix) {
  const response = http.post(
    EVENT_CREATE_URL,
    {
      "Evento[tituloevento]": `${EVENT_TITLE_PREFIX}-${suffix}`,
      "Evento[local]": "Grafana",
      "Evento[instituicaopromotora]": "k6",
      "Evento[cidade]": "Anápolis",
      "Evento[datainicial]": TEST_DATE,
      "Evento[datafinal]": "01/11/2026",
      "Evento[datainscricaoi]": TEST_DATE,
      "Evento[datainscricaof]": "15/10/2026",
      "Evento[printcertificate]": "0",
    },
    requestParams("upload", "event-create", jar)
  );

  const id = idFromRedirect(response);
  const ok = response.status === 302 && id !== null;

  check(response, { "evento de teste criado": () => ok }, { flow: "upload" });
  return ok ? id : null;
}

function createSubevento(jar, eventId, suffix) {
  const uploadFileName = `certificado-test-k6-${suffix}.pdf`;

  const response = http.post(
    SUBEVENTO_CREATE_URL,
    {
      "Subevento[id_evento]": eventId,
      "Subevento[tipo]": SUBEVENTO_TYPE_CURSOS,
      "Subevento[nome]": `${SUBEVENTO_NAME_PREFIX}-${suffix}`,
      "Subevento[generomasculino]": "0",
      "Subevento[filepath]": http.file(UPLOAD_FILE, uploadFileName, "application/pdf"),
    },
    requestParams("upload", "subevento-create", jar)
  );

  const id = idFromRedirect(response);
  const ok = response.status === 302 && id !== null;

  check(response, { "subevento com upload criado": () => ok }, { flow: "upload" });
  return ok ? id : null;
}

function createActivity(jar, eventId, subeventoId, suffix) {
  const response = http.post(
    ATIVIDADE_CREATE_URL,
    {
      "Atividade[tema]": `${EVENT_TITLE_PREFIX}-${suffix}`,
      "Atividade[qtdhoras]": "1",
      "Atividade[vagasdisponiveis]": "1",
      "Atividade[id_subevento]": subeventoId,
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
    requestParams("upload", "atividade-create", jar)
  );

  const id = idFromRedirect(response);
  const ok = response.status === 302 && id !== null;

  check(response, { "atividade de verificacao criada": () => ok }, { flow: "upload" });
  return ok ? id : null;
}

function verifyFile(activityId) {
  const jar = http.cookieJar();
  jar.clear(BASE_URL);

  if (!login(jar, "arquivo")) {
    fileVerifyFailure.add(1, { flow: "arquivo", reason: "login_failed" });
    return;
  }

  const response = http.get(CERTPAL_URL(activityId), requestParams("arquivo", "certpal-pdf", jar));
  const contentType = (response.headers["Content-Type"] || response.headers["content-type"] || "").toLowerCase();
  const location = locationOf(response);
  const ok = response.status === 200 && contentType.includes("application/pdf");

  if (ok) {
    fileVerifySuccess.add(1, { flow: "arquivo" });
  } else {
    let reason = `http_${response.status}`;
    if (response.status >= 300 && response.status < 400 && location.includes("user/login")) {
      reason = "session_lost";
    }
    fileVerifyFailure.add(1, { flow: "arquivo", reason });
  }

  check(response, {
    "arquivo PDF visivel via certpal (200 application/pdf)": () => ok,
  }, { flow: "arquivo" });
}

// O Prometheus so cria a serie de um Counter quando ele e incrementado; sem um valor 0 inicial,
// a primeira falha aparece como serie nova com valor 1 e rate()/increase() nao a enxergam.
// Emitir 0 para cada motivo conhecido (dentro do mesmo group) cria as series desde o inicio.
function initFailureCounters() {
  ["login_failed", "session_lost", "http_500", "unexpected_status"].forEach((reason) =>
    sessionLoss.add(0, { flow: "session", reason }));
  ["login_failed", "event_failed", "upload_failed"].forEach((reason) =>
    uploadFailure.add(0, { flow: "upload", reason }));
  ["login_failed", "session_lost", "http_500", "activity_failed"].forEach((reason) =>
    fileVerifyFailure.add(0, { flow: "arquivo", reason }));
}

export function sessionTest() {
  const jar = http.cookieJar();
  jar.clear(BASE_URL);

  group("session", () => {
    initFailureCounters();
    const anonymous = http.get(PROFILE_URL, requestParams("session", "profile-anon", jar));
    check(anonymous, {
      "anonimo redireciona para login": (r) => {
        const location = locationOf(r);
        return r.status >= 300 && r.status < 400 && location.includes("user/login");
      },
    }, { flow: "session" });

    if (!login(jar, "session")) {
      sessionLoss.add(1, { flow: "session", reason: "login_failed" });
      return;
    }

    const authenticated = http.get(PROFILE_URL, requestParams("session", "profile-auth", jar));
    const location = locationOf(authenticated);
    const isSessionOk = authenticated.status === 200 && !location.includes("user/login");

    if (isSessionOk) {
      sessionSuccess.add(1, { flow: "session" });
    } else {
      let reason = "unexpected_status";
      if (authenticated.status === 500) {
        reason = "http_500";
      } else if (authenticated.status >= 300 && authenticated.status < 400 && location.includes("user/login")) {
        reason = "session_lost";
      }
      sessionLoss.add(1, { flow: "session", reason });
    }

    check(authenticated, {
      "perfil autenticado mantem sessao (200 OK)": () => isSessionOk,
    }, { flow: "session" });
  });

  sleep(1);
}

export function uploadTest() {
  const suffix = `${__VU}-${__ITER}-${Date.now()}`;
  const jar = http.cookieJar();
  jar.clear(BASE_URL);

  group("upload", () => {
    initFailureCounters();
    if (!login(jar, "upload")) {
      uploadFailure.add(1, { flow: "upload", reason: "login_failed" });
      return;
    }

    const eventId = createEvent(jar, suffix);
    if (!eventId) {
      uploadFailure.add(1, { flow: "upload", reason: "event_failed" });
      return;
    }

    const subeventoId = createSubevento(jar, eventId, suffix);
    if (!subeventoId) {
      uploadFailure.add(1, { flow: "upload", reason: "upload_failed" });
      return;
    }

    uploadSuccess.add(1, { flow: "upload" });

    const atividadeId = createActivity(jar, eventId, subeventoId, suffix);
    if (!atividadeId) {
      fileVerifyFailure.add(1, { flow: "arquivo", reason: "activity_failed" });
      return;
    }

    verifyFile(atividadeId);
  });

  sleep(1);
}

export function smokeTest() {
  sessionTest();
  uploadTest();
}
