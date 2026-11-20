#!/usr/bin/env python3
"""
Requête Flexcube en lecture seule (Oracle, mode thin) pour les scripts « requête » des produits divers.

Entrée JSON (stdin) :
{
  "host": "...", "port": 1522, "service_name": "...",
  "username": "...", "password": "...",
  "sql": "SELECT ... WHERE NO_COMPTE = :NUMERO_COMPTE",
  "binds": {"NUMERO_COMPTE": "251541568001"},
  "max_rows": 50,
  "timeout_ms": 30000
}

Sortie JSON (stdout) : { "ok": true, "rows": [ {"COLONNE": valeur, ...} ] }
"""

from __future__ import annotations

import datetime
import decimal
import json
import re
import sys


def serialisable(value):
    if isinstance(value, decimal.Decimal):
        return int(value) if value == value.to_integral_value() else float(value)
    if isinstance(value, (datetime.datetime, datetime.date)):
        return value.isoformat()
    if isinstance(value, bytes):
        return value.decode("utf-8", errors="replace")
    return value


def main() -> int:
    try:
        payload = json.load(sys.stdin)
    except json.JSONDecodeError as exc:
        print(json.dumps({"ok": False, "error": f"JSON invalide: {exc}"}))
        return 1

    host = str(payload.get("host") or "").strip()
    port = int(payload.get("port") or 1522)
    service = str(payload.get("service_name") or "").strip()
    user = str(payload.get("username") or "").strip()
    password = str(payload.get("password") or "")
    schema = str(payload.get("schema") or "").strip()
    sql = str(payload.get("sql") or "").strip()
    binds = payload.get("binds") or {}
    max_rows = int(payload.get("max_rows") or 50)
    timeout_ms = int(payload.get("timeout_ms") or 30000)

    if not all([host, service, user, sql]) or password == "":
        print(json.dumps({"ok": False, "error": "Paramètres Oracle incomplets."}))
        return 1

    if schema and not re.match(r"^[A-Za-z][A-Za-z0-9_$#]*$", schema):
        print(json.dumps({"ok": False, "error": "ORACLE_SCHEMA invalide."}))
        return 1

    try:
        import oracledb
    except ImportError:
        print(json.dumps({
            "ok": False,
            "error": "Module Python oracledb manquant. Installez : storage/app/oracle-venv/bin/pip install oracledb",
        }))
        return 1

    dsn = oracledb.makedsn(host, port, service_name=service)

    try:
        with oracledb.connect(user=user, password=password, dsn=dsn) as conn:
            conn.call_timeout = timeout_ms
            with conn.cursor() as cur:
                if schema:
                    cur.execute(f"ALTER SESSION SET CURRENT_SCHEMA = {schema}")
                cur.execute("SET TRANSACTION READ ONLY")
                cur.execute(sql, binds)
                columns = [str(d[0]).upper() for d in cur.description or []]
                rows = []
                for record in cur.fetchmany(max_rows):
                    rows.append({col: serialisable(val) for col, val in zip(columns, record)})
            conn.rollback()
    except Exception as exc:  # noqa: BLE001
        print(json.dumps({"ok": False, "error": str(exc)}, ensure_ascii=False))
        return 1

    print(json.dumps({"ok": True, "rows": rows}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
