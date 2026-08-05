#!/usr/bin/env bash
# Démarre le gateway Reconciliation (reconc.py) sur le port 8002.
set -euo pipefail

GATEWAY_DIR="${RECONCILIATION_GATEWAY_DIR:-$HOME/Téléchargements/reconciliation}"
PORT="${RECONCILIATION_GATEWAY_PORT:-8002}"

if [[ ! -f "$GATEWAY_DIR/reconc.py" ]]; then
  echo "reconc.py introuvable dans : $GATEWAY_DIR"
  echo "Définissez RECONCILIATION_GATEWAY_DIR vers le dossier du gateway."
  exit 1
fi

cd "$GATEWAY_DIR"

if [[ -x "$GATEWAY_DIR/.venv/bin/uvicorn" ]]; then
  exec "$GATEWAY_DIR/.venv/bin/uvicorn" reconc:app --host 127.0.0.1 --port "$PORT"
fi

if command -v uvicorn >/dev/null 2>&1; then
  exec uvicorn reconc:app --host 127.0.0.1 --port "$PORT"
fi

echo "uvicorn introuvable. Activez le venv du gateway ou : pip install uvicorn fastapi"
exit 1
