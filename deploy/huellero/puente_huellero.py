#!/usr/bin/env python3
"""
Puente del huellero ZKTeco -> Kyrios.

Corre en una computadora de la MISMA red que el reloj (el sistema está en internet y no puede llegar al reloj).
Cada vez que se ejecuta:
  1. se conecta al reloj por red (puerto 4370),
  2. lee las marcaciones,
  3. envía a Kyrios solo las que son posteriores a la última enviada,
  4. guarda hasta dónde llegó (archivo de estado) para no repetir.

Requisitos:   pip install pyzk requests
Uso:          python puente_huellero.py --reloj 192.168.1.201 --url https://kyrios.leongrup.com --token EL_TOKEN
Programarlo:  Programador de tareas de Windows, cada 10 o 15 minutos.

AVISO: este script se escribió a partir de la documentación de la librería pyzk y todavía no se probó con el
reloj LX14 del cliente. Pruébelo primero con --solo-mostrar (no envía nada) y revise que las fechas salgan bien.
"""
import argparse
import json
import os
import sys
from datetime import datetime

try:
    from zk import ZK
    import requests
except ImportError:
    sys.exit("Faltan librerías: pip install pyzk requests")

ESTADO = os.path.join(os.path.dirname(os.path.abspath(__file__)), "puente_huellero.estado.json")
LOTE = 1000


def cargar_estado():
    try:
        with open(ESTADO, encoding="utf-8") as f:
            return json.load(f)
    except (OSError, ValueError):
        return {}


def guardar_estado(estado):
    with open(ESTADO, "w", encoding="utf-8") as f:
        json.dump(estado, f)


def main():
    ap = argparse.ArgumentParser(description="Envía las marcaciones del huellero ZKTeco a Kyrios.")
    ap.add_argument("--reloj", required=True, help="IP del reloj, ej. 192.168.1.201")
    ap.add_argument("--puerto", type=int, default=4370)
    ap.add_argument("--clave", type=int, default=0, help="Clave de comunicación del reloj (0 si no tiene)")
    ap.add_argument("--url", required=True, help="Dirección de Kyrios, ej. https://kyrios.leongrup.com")
    ap.add_argument("--token", required=True, help="Valor de HUELLERO_TOKEN del servidor")
    ap.add_argument("--solo-mostrar", action="store_true", help="Muestra las marcaciones nuevas sin enviarlas")
    a = ap.parse_args()

    estado = cargar_estado()
    ultima = datetime.fromisoformat(estado["ultima"]) if estado.get("ultima") else None

    zk = ZK(a.reloj, port=a.puerto, timeout=15, password=a.clave, force_udp=False, ommit_ping=True)
    conn = None
    try:
        conn = zk.connect()
        marcaciones = conn.get_attendance()
    finally:
        if conn:
            conn.disconnect()

    nuevas = sorted(
        ({"pin": str(m.user_id), "fecha_hora": m.timestamp.strftime("%Y-%m-%d %H:%M:%S")}
         for m in marcaciones if ultima is None or m.timestamp > ultima),
        key=lambda x: x["fecha_hora"],
    )
    print(f"{len(marcaciones)} marcaciones en el reloj, {len(nuevas)} nuevas.")
    if a.solo_mostrar or not nuevas:
        for m in nuevas[:20]:
            print("  ", m["pin"], m["fecha_hora"])
        return

    for i in range(0, len(nuevas), LOTE):
        lote = nuevas[i:i + LOTE]
        r = requests.post(
            a.url.rstrip("/") + "/api/huellero/marcaciones",
            json={"marcaciones": lote},
            headers={"Authorization": "Bearer " + a.token, "Accept": "application/json"},
            timeout=60,
        )
        if r.status_code != 200:
            sys.exit(f"Kyrios respondió {r.status_code}: {r.text[:300]}")
        print("Kyrios:", r.json())
        estado["ultima"] = lote[-1]["fecha_hora"].replace(" ", "T")
        guardar_estado(estado)


if __name__ == "__main__":
    main()
