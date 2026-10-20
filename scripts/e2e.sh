#!/usr/bin/env bash
# Sobe a stack inteira do jeito q o README manda (com o outbox-gateway-demo de verdade) e confere
# o caminho de ponta a ponta: login, API com token e lembrete chegando no gateway uma vez só.
# Roda no CI e na máquina: bash scripts/e2e.sh
# Usa outro nome de projeto do compose, então o down -v do fim não apaga o banco de desenvolvimento.
set -euo pipefail

export COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-multitenant-invoicing-e2e}"
# UID é readonly no bash, mas dá pra exportar; sem isso a imagem sai com 1000 e o bind mount quebra no CI
export UID GID="${GID:-$(id -g)}"
BASE="http://localhost:8000"

dc() { docker compose --profile outbox "$@"; }
app_sql() { dc exec -T db psql -U invoicing -d invoicing -tAc "$1"; }
gw_sql() { dc exec -T db psql -U invoicing -d outbox -tAc "$1"; }

cleanup() { dc down -v --remove-orphans >/dev/null 2>&1 || true; }
trap cleanup EXIT

fail() {
    echo "FALHOU: $*"
    dc logs --tail=60 app queue outbox-gateway || true
    exit 1
}

# espera até o comando dar certo, no máximo $1 segundos
wait_for() {
    local secs=$1 desc=$2; shift 2
    for _ in $(seq 1 "$secs"); do
        if "$@" >/dev/null 2>&1; then return 0; fi
        sleep 1
    done
    fail "$desc não ficou pronto em ${secs}s"
}

# predicados pro wait_for: rodam a consulta de novo a cada tentativa
queue_empty() { [ "$(app_sql 'select count(*) from jobs')" = 0 ]; }
gateway_sent() { [ "$(gw_sql "select count(*) from outbox where status = 'sent'")" = "$1" ]; }

expect() {
    local desc=$1 got=$2 want=$3
    [ "$got" = "$want" ] || fail "$desc: veio '$got', esperado '$want'"
    echo "ok  $desc ($got)"
}

dc up -d --build
wait_for 300 "app" curl -fsS "$BASE/login"
dc exec -T app php artisan db:seed --force >/dev/null

expect "GET /login" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/login")" 200

token=$(curl -fsS -X POST "$BASE/api/v1/tokens" -H 'Accept: application/json' \
    -d email=dona@acme.test -d password=password -d device_name=e2e |
    sed -E 's/.*"token":"([^"]+)".*/\1/')
expect "API com token" "$(curl -s -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $token" -H 'Accept: application/json' "$BASE/api/v1/invoices")" 200
expect "API sem token" "$(curl -s -o /dev/null -w '%{http_code}' -H 'Accept: application/json' "$BASE/api/v1/invoices")" 401

# o gateway cria as tabelas dele na subida
wait_for 120 "gateway" gw_sql "select 1 from outbox limit 1"

dc exec -T app php artisan invoices:mark-overdue
wait_for 120 "fila de lembretes" queue_empty
sent=$(app_sql "select count(*) from reminders where outcome = 'sent'")
[ "$sent" -gt 0 ] || fail "nenhum lembrete enviado"
echo "ok  lembretes enviados pelo Laravel ($sent)"

wait_for 60 "entrega no provedor" gateway_sent "$sent"
expect "mensagens no gateway" "$(gw_sql 'select count(*) from messages')" "$sent"
expect "chaves invoice-{id}-{data}" "$(gw_sql "select count(*) from messages where idempotency_key ~ '^invoice-[0-9]+-[0-9]{4}-[0-9]{2}-[0-9]{2}$'")" "$sent"

# mesmo dia de novo: nada pode sair duplicado
dc exec -T app php artisan invoices:mark-overdue
wait_for 120 "fila da segunda rodada" queue_empty
expect "segunda rodada não duplica no gateway" "$(gw_sql 'select count(*) from messages')" "$sent"

echo "E2E ok"
