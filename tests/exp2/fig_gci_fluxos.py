"""Figura dos fluxos de teste do k6 (sessao e upload) e do estado acessado em cada etapa.

Uso: python fig_gci_fluxos.py <saida.png>
Reflete tests/k6/loadtest.js (sessionTest e uploadTest).
"""
import sys
import textwrap
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
from matplotlib.patches import FancyBboxPatch, Circle, FancyArrowPatch

SAIDA = sys.argv[1] if len(sys.argv) > 1 else "fig_gci_fluxos.png"

COR = {
    "Sessão": ("#0072B2", "#DCE9F5"),
    "Banco": ("#009E73", "#DAF0E8"),
    "Arquivo": ("#D55E00", "#FBE3D2"),
    "Sem estado": ("#44546A", "#EEF1F6"),
}

FLUXOS = [
    ("Fluxo de sessão (8 usuários virtuais)", [
        ("GET", "user/profile", "Sem cookie: espera redirecionamento (302) ao login", ["Sem estado"]),
        ("GET", "user/login", "Exibe o formulário de login", ["Sem estado"]),
        ("POST", "user/login", "Valida usuário e senha, cria a sessão e o cookie", ["Sessão", "Banco"]),
        ("GET", "user/profile", "Lê a sessão: 200 = mantida; 302 = perdida", ["Sessão"]),
    ]),
    ("Fluxo de upload e verificação do arquivo (3 usuários virtuais)", [
        ("POST", "user/login", "Abre o formulário e autentica; cria a sessão", ["Sessão", "Banco"]),
        ("POST", "evento/create", "Insere o evento", ["Banco"]),
        ("POST", "subevento/create", "Envia o PDF, insere o subevento e grava o arquivo", ["Banco", "Arquivo"]),
        ("POST", "atividade/create", "Insere a atividade", ["Banco"]),
        ("POST", "user/login", "Descarta o cookie e autentica de novo, em sessão limpa", ["Sessão", "Banco"]),
        ("GET", "atividade/certpal", "Lê o PDF do disco: 200 PDF = acessível; 500 = inacessível", ["Arquivo", "Banco"]),
    ]),
]

LARG = 10.0
ALT_LINHA = 0.78
ESP = 0.22
ALT_TITULO = 0.6
ALT_LEGENDA = 0.95

n_linhas = sum(len(f[1]) for f in FLUXOS)
altura = len(FLUXOS) * ALT_TITULO + n_linhas * (ALT_LINHA + ESP) + 0.35 * len(FLUXOS) + ALT_LEGENDA

fig = plt.figure(figsize=(LARG, altura))
ax = fig.add_axes([0, 0, 1, 1])
ax.set_xlim(0, LARG)
ax.set_ylim(0, altura)
ax.axis("off")

X_NUM = 0.45
X_REQ = 0.9
L_REQ = 3.2
X_DESC = X_REQ + L_REQ + 0.2
L_DESC = 3.3
X_CHIP = X_DESC + L_DESC + 0.2

y = altura - 0.15
for titulo, passos in FLUXOS:
    y -= 0.45
    ax.text(0.15, y, titulo, fontsize=17, fontweight="bold", va="center", ha="left")
    y -= 0.35
    for i, (met, rota, desc, estados) in enumerate(passos, 1):
        topo = y
        base = y - ALT_LINHA
        centro = (topo + base) / 2
        cor_borda = COR[estados[0]][0]
        cor_fundo = COR[estados[0]][1]
        ax.add_patch(Circle((X_NUM, centro), 0.24, facecolor="#222222", edgecolor="none", zorder=3))
        ax.text(X_NUM, centro, str(i), color="white", fontsize=14, fontweight="bold", ha="center", va="center", zorder=4)
        ax.add_patch(FancyBboxPatch((X_REQ, base), L_REQ, ALT_LINHA, boxstyle="round,pad=0,rounding_size=0.12",
                                    facecolor=cor_fundo, edgecolor=cor_borda, linewidth=2.2, zorder=2))
        ax.text(X_REQ + 0.2, centro, met, fontsize=14, fontweight="bold", color=cor_borda, ha="left", va="center", zorder=3)
        ax.text(X_REQ + 1.0, centro, rota, fontsize=14, family="monospace", ha="left", va="center", zorder=3)
        ax.text(X_DESC, centro, textwrap.fill(desc, 33), fontsize=12.5, ha="left", va="center",
                linespacing=1.25, zorder=3)
        # chips de estado
        xc = X_CHIP
        for e in estados:
            borda, fundo = COR[e]
            larg = 0.2 + 0.105 * len(e)
            ax.add_patch(FancyBboxPatch((xc, centro - 0.17), larg, 0.34, boxstyle="round,pad=0,rounding_size=0.1",
                                        facecolor=fundo, edgecolor=borda, linewidth=1.8, zorder=2))
            ax.text(xc + larg / 2, centro, e, fontsize=11.5, color="#111111", ha="center", va="center", zorder=3)
            xc += larg + 0.08
        if i < len(passos):
            ax.add_patch(FancyArrowPatch((X_REQ + 0.5, base), (X_REQ + 0.5, base - ESP),
                                         arrowstyle="-|>", mutation_scale=14, color="#333333", linewidth=1.8, zorder=1))
        y = base - ESP
    y -= 0.1

# legenda
ax.text(0.15, 0.62, "Estado acessado:", fontsize=12.5, fontweight="bold", va="center")
xl = 2.2
rotulos = {"Sessão": "Sessão", "Banco": "Banco de dados",
           "Arquivo": "Arquivo enviado", "Sem estado": "Sem estado"}
for k in ["Sessão", "Banco", "Arquivo", "Sem estado"]:
    borda, fundo = COR[k]
    ax.add_patch(FancyBboxPatch((xl, 0.5), 0.3, 0.24, boxstyle="round,pad=0,rounding_size=0.06",
                                facecolor=fundo, edgecolor=borda, linewidth=1.8))
    ax.text(xl + 0.4, 0.62, rotulos[k], fontsize=11.5, va="center")
    xl += 0.4 + 0.1 * len(rotulos[k]) + 0.35
ax.text(0.15, 0.2, "Rotas no formato index.php?r=controlador/ação. Pausa de 1 s entre as iterações de cada fluxo.",
        fontsize=11, style="italic", color="#444444", va="center")

fig.savefig(SAIDA, dpi=220, facecolor="white")
print("ok", SAIDA)
