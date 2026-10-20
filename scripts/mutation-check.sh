#!/bin/sh
# Quebra de propósito cada proteção principal, roda a suíte e restaura o arquivo.
# A proteção só vale se pelo menos um teste falhar. Rodar dentro do container:
#   docker compose exec app sh scripts/mutation-check.sh
set -u

current=""

# se o script for interrompido no meio, a proteção quebrada não pode ficar no código
restore() {
    if [ -n "$current" ]; then
        cp /tmp/mutation.bak "$current"
        current=""
    fi
}
trap restore EXIT
trap 'restore; exit 130' INT
trap 'restore; exit 143' TERM

mutate() {
    name="$1"; file="$2"; from="$3"; to="$4"
    cp "$file" /tmp/mutation.bak
    current="$file"
    sed -i "s|$from|$to|" "$file"
    if cmp -s "$file" /tmp/mutation.bak; then
        current=""
        echo "ERRO: mutação '$name' não alterou $file"; exit 1
    fi
    summary=$(vendor/bin/pest --colors=never 2>&1 | grep -E '^ *Tests:')
    restore
    case "$summary" in
        *failed*) echo "PEGO   $name ->$summary" ;;
        *) echo "ESCAPOU $name ->$summary"; status=1 ;;
    esac
}

status=0
mutate "sem escopo de tenant" app/Infrastructure/Persistence/Models/Concerns/BelongsToCompany.php \
    'static::addGlobalScope(new CompanyScope);' '// sem escopo'
mutate "escopo aberto sem usuário" app/Infrastructure/Persistence/Models/Scopes/CompanyScope.php \
    "\$builder->whereRaw('false');" '// libera tudo'
mutate "sem lock único no job" app/Infrastructure/Queue/SendInvoiceReminderJob.php \
    'implements ShouldBeUnique, ShouldQueue' 'implements ShouldQueue'
mutate "envio sem Idempotency-Key" app/Infrastructure/Outbox/OutboxReminderSender.php \
    "->withHeaders(\['Idempotency-Key' => \$key->value\])" ''
mutate "4xx tratado como retry" app/Infrastructure/Outbox/OutboxReminderSender.php \
    'default => DeliveryOutcome::Rejected' 'default => DeliveryOutcome::RetryLater'
mutate "retry_after menor q o timeout" config/queue.php \
    "env('DB_QUEUE_RETRY_AFTER', 90)" "env('DB_QUEUE_RETRY_AFTER', 20)"
exit $status
