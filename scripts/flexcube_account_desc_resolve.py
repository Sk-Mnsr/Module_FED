#!/usr/bin/env python3
"""
Intitulés comptes Flexcube via Oracle — mode thin.

- Clients : STTM_CUST_ACCOUNT.AC_DESC (CUST_AC_NO)
- GL : GLTM_GLMASTER.GL_DESC (GL_CODE)

Entrée JSON (stdin) :
{
  "host": "...", "port": 1522, "service_name": "...",
  "username": "...", "password": "...", "schema": "CFSFCUBS145",
  "accounts": ["251530644001", "379200000180"]
}

Sortie JSON (stdout) :
{ "ok": true, "map": { "251530644001": "...", "379200000180": "..." } }
"""

from __future__ import annotations

import json
import re
import sys


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
    schema = str(payload.get("schema") or "CFSFCUBS145").strip()
    accounts = [str(a).strip() for a in (payload.get("accounts") or []) if str(a).strip()]

    if not all([host, service, user]) or password == "":
        print(json.dumps({"ok": False, "error": "Paramètres Oracle incomplets."}))
        return 1

    if not re.match(r"^[A-Za-z][A-Za-z0-9_$#]*$", schema):
        print(json.dumps({"ok": False, "error": "ORACLE_SCHEMA invalide."}))
        return 1

    if not accounts:
        print(json.dumps({"ok": True, "map": {}}))
        return 0

    try:
        import oracledb
    except ImportError:
        print(json.dumps({
            "ok": False,
            "error": "Module Python oracledb manquant. Installez : storage/app/oracle-venv/bin/pip install oracledb",
        }))
        return 1

    dsn = oracledb.makedsn(host, port, service_name=service)

    binds = {f"a{i}": acc for i, acc in enumerate(accounts)}
    placeholders = ", ".join(f":a{i}" for i in range(len(accounts)))

    sql = f"""
SELECT CUST_AC_NO AS CPT, AC_DESC AS LIBELLE
FROM {schema}.STTM_CUST_ACCOUNT
WHERE CUST_AC_NO IN ({placeholders})
UNION ALL
SELECT GL_CODE AS CPT, GL_DESC AS LIBELLE
FROM {schema}.GLTM_GLMASTER
WHERE GL_CODE IN ({placeholders})
"""

    try:
        with oracledb.connect(user=user, password=password, dsn=dsn) as conn:
            with conn.cursor() as cur:
                cur.execute(sql, binds)
                result: dict[str, str] = {}
                for cpt, libelle in cur:
                    key = str(cpt).strip() if cpt is not None else ""
                    desc = str(libelle).strip() if libelle is not None else ""
                    if key and desc and key not in result:
                        result[key] = desc
    except Exception as exc:  # noqa: BLE001
        print(json.dumps({"ok": False, "error": str(exc)}))
        return 1

    print(json.dumps({"ok": True, "map": result}, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
