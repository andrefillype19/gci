#!/usr/bin/env python3
"""CPU media entre dois snapshots (inicio e fim do platao) de uma execucao do Experimento 2.

Uso (no gerador):  python3 ~/gci/tests/exp2/cpu_summary.py ~/results/cal-1-r1
Le <pasta>/snap-inicio.txt e <pasta>/snap-fim.txt (gerados por snapshot.sh).

- Pods da aplicacao: delta de container_cpu_usage_seconds_total (container gci-app) / delta de tempo,
  em milinucleos (m). Alvo da calibracao: 150 a 175 m por pod (60 a 70% do request de 250 m).
- Hosts: ocupacao = 1 - (idle + iowait) / total, a partir de node_cpu_seconds_total.
"""
import collections
import os
import re
import sys


def load(path):
    t = None
    sect = None
    host = None
    cpu = collections.defaultdict(lambda: collections.defaultdict(float))
    pods = collections.defaultdict(float)
    with open(path, encoding="utf-8", errors="replace") as fh:
        for raw in fh:
            line = raw.rstrip("\n")
            if line.startswith("# snapshot"):
                t = float(re.search(r"epoch=([0-9.]+)", line).group(1))
            elif line.startswith("## node_exporter"):
                sect, host = "node", line.split()[2]
            elif line.startswith("## cadvisor"):
                sect = "cadv"
            elif line.startswith("## kubectl top"):
                sect = None
            elif line.startswith("#"):
                continue
            elif sect == "node" and line.startswith("node_cpu_seconds_total"):
                m = re.match(r'node_cpu_seconds_total\{[^}]*mode="([^"]+)"[^}]*\}\s+([0-9.eE+-]+)', line)
                if m:
                    cpu[host][m.group(1)] += float(m.group(2))
            elif sect == "cadv" and line.startswith("container_cpu_usage_seconds_total") and 'container="gci-app"' in line:
                pod = re.search(r'pod="([^"]+)"', line).group(1)
                pods[pod] += float(re.search(r"\}\s+([0-9.eE+-]+)", line).group(1))
    return t, cpu, pods


def main():
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    folder = sys.argv[1]
    t0, cpu0, pods0 = load(os.path.join(folder, "snap-inicio.txt"))
    t1, cpu1, pods1 = load(os.path.join(folder, "snap-fim.txt"))
    dt = t1 - t0
    print(f"Intervalo entre os snapshots: {dt:.0f} s\n")

    print("Pods da aplicacao (milinucleos medios no intervalo):")
    vals = []
    for pod in sorted(set(pods0) & set(pods1)):
        m = (pods1[pod] - pods0[pod]) / dt * 1000
        vals.append(m)
        print(f"  {pod:<28} {m:7.1f} m  ({m / 250 * 100:5.1f}% do request)")
    if vals:
        mean = sum(vals) / len(vals)
        print(f"  {'media por pod':<28} {mean:7.1f} m  ({mean / 250 * 100:5.1f}% do request)  | total {sum(vals):.1f} m")
    else:
        print("  (nenhum pod em comum entre os dois snapshots)")

    print("\nHosts (ocupacao media de CPU no intervalo):")
    for host in cpu0:
        if host not in cpu1:
            continue
        d = {k: cpu1[host][k] - cpu0[host].get(k, 0.0) for k in cpu1[host]}
        total = sum(d.values())
        idle = d.get("idle", 0.0) + d.get("iowait", 0.0)
        if total > 0:
            print(f"  {host:<10} {100 * (1 - idle / total):5.1f}%")


if __name__ == "__main__":
    main()
