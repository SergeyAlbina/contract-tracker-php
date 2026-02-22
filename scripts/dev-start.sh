#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

HOST="${DEV_HOST:-127.0.0.1}"
PORT="${DEV_PORT:-8000}"
PHP_BIN="${PHP_BIN:-php}"

echo "[dev-start] Project root: $ROOT_DIR"

if [[ ! -f ".env" && -f ".env.example" ]]; then
  cp .env.example .env
  echo "[dev-start] Created .env from .env.example"
fi

if command -v mysqld >/dev/null 2>&1 || command -v mysql >/dev/null 2>&1; then
  if pgrep -x mysqld >/dev/null 2>&1; then
    echo "[dev-start] MySQL is already running"
  else
    echo "[dev-start] Starting MySQL service..."
    sudo service mysql start >/dev/null
    echo "[dev-start] MySQL started"
  fi
fi

if [[ -f ".env" ]]; then
  if "$PHP_BIN" -r 'require "autoload.php"; App\Shared\Utils\Env::load(__DIR__."/.env"); try { App\Infrastructure\Db\PdoFactory::create(); echo "ok"; } catch (Throwable $e) { echo "fail: ".$e->getMessage(); exit(1); }' >/tmp/contract-tracker-db-check.log 2>&1; then
    echo "[dev-start] DB connection check: ok"
  else
    echo "[dev-start] DB connection check failed:"
    cat /tmp/contract-tracker-db-check.log
    echo "[dev-start] Continuing to start HTTP server anyway"
  fi
fi

echo "[dev-start] Starting server at http://$HOST:$PORT"
exec "$PHP_BIN" -S "$HOST:$PORT" -t public
