# multitenant-invoicing

A small multi-tenant invoicing SaaS in Laravel 13 + Inertia + React. Each company has its users (owner or member), clients and invoices in BRL. Every day a command marks overdue invoices and sends a WhatsApp reminder through [outbox-gateway-demo](https://github.com/MiguelSanzBr/outbox-gateway-demo), my other project.

[Versão em português](README.md)

## Why this repo exists

It brings two lessons from my work into public code:

1. **A cross-tenant leak of the IDOR kind in a CRM.** Changing the id in the URL was enough to see another company's data. Here isolation does not depend on every controller remembering to filter: the filter lives in the model and fails closed.
2. **A duplicated send caused by a queue retry.** The job took too long, the queue assumed it had died, delivered it again and the message went out twice. Here the job is unique per invoice and per day, the queue `retry_after` is greater than the job `timeout`, and the send carries an idempotency key so the gateway drops repeats.

Both are solved by design, and each one has tests that break if someone removes the protection (see [Mutation check](#mutation-check)).

## Architecture

Hexagonal, the Laravel way: a pure PHP domain in the center, the framework at the edges.

```
                 ┌──────────────────────────── Http / Console (driving adapters) ─────────────────┐
                 │  Web controllers (Inertia)  ·  /api/v1 (Sanctum)  ·  invoices:mark-overdue     │
                 └───────────────┬───────────────────────────────────────────────┬───────────────┘
                                 │ writes                                        │ reads
                                 ▼                                               │ (straight to Eloquent,
                 ┌─────────── Application (use cases) ──────────────┐            │  tenant scope applied)
                 │ CreateInvoice · UpdateInvoice · MarkInvoicePaid  │            │
                 │ DeleteInvoice · SaveClient · DeleteClient        │            │
                 │ MarkOverdueInvoices · SendInvoiceReminder        │            │
                 └───────────────┬──────────────────────────────────┘            │
                                 ▼                                               │
                 ┌──────────────── Domain (pure PHP) ───────────────┐            │
                 │ Invoice (draft→open→paid/overdue) · Client       │            │
                 │ Money · DueDate · PhoneNumber · IdempotencyKey   │            │
                 │ ports: InvoiceRepository · ClientRepository      │            │
                 │ ReminderSender · ReminderLog · ReminderQueue     │            │
                 │ TenantContext                                    │            │
                 └───────────────▲──────────────────────────────────┘            │
                                 │ implements                                    ▼
                 ┌──────────────── Infrastructure (driven adapters) ──────────────────────────────┐
                 │ Eloquent models + tenant scope · Eloquent repositories                         │
                 │ OutboxReminderSender (HTTP) · SendInvoiceReminderJob (queue) · AuthTenantContext │
                 └────────────────────────────────────────────────────────────────────────────────┘
```

- `app/Domain`: entities, value objects and interfaces. No `Illuminate`, `Carbon` or other layers. An invoice only changes status through methods (`open`, `markPaid`, `markOverdue`, `edit`) and rejects invalid transitions with `RuleViolation`.
- `app/Application`: one use case per class, depending only on domain ports.
- `app/Infrastructure`: Eloquent, queue, HTTP and auth. The tenant global scope lives here because it is a persistence detail.
- `app/Http` and `app/Console`: controllers, policies, form requests, resources and the daily command. Every write goes through a use case.

The dependency rule is checked by Pest architecture tests (`tests/Unit/ArchTest.php`), which run with the rest of the suite in CI.

**Why.** The rules that matter (status transitions, integer cents, the idempotency key, what to do with each gateway response) are testable without a database or HTTP, using in-memory repositories. Laravel keeps doing what it is good at: routing, validation, queues, Eloquent.

**Where I was pragmatic.** Reads (lists, dashboard, history) go straight to Eloquent inside the controller, with the tenant scope applied. A use case and a DTO for every listing would be ceremony with no rule inside. Policies stay as "who can" authorization (same company, owner to delete) and the domain answers "is this allowed in this state?".

## Tenant isolation

- `BelongsToCompany` (trait) + `CompanyScope` (global scope) on every model with `company_id`: `Client`, `Invoice` and `Reminder`.
- With a logged-in user, queries filter by that user's company. **Without a user the scope returns nothing**, not everything. Jobs and commands that must cross companies use explicitly named methods (`findAcrossTenants`, `openPastDue`) that are easy to spot in review.
- On create, `company_id` always comes from the logged-in user and the payload value is ignored.
- Route model binding goes through the scope, so another company's id is a 404, same as a missing id. You cannot tell whether the id exists.
- The invoice `client_id` is validated against the user's company in the form request and again in the use case.
- Policies on every action, web and API.
- One test walks `app/Infrastructure/Persistence/Models` and fails if any model with `company_id` does not use the scope. `User` is the only exception, because login looks the user up before a tenant exists.

## Idempotent reminders and outbox-gateway-demo

```
09:00 invoices:mark-overdue ── marks open invoices with a past due date as overdue
          │
          └─ for each overdue invoice: SendInvoiceReminderJob(invoice_id, day)
                  ShouldBeUnique, uniqueId = invoice-{id}-{Y-m-d}
                  timeout 30s, queue retry_after 90s, 5 tries with backoff
                        │
                        ▼
              SendInvoiceReminder (use case)
                ├─ invoice no longer overdue? don't send
                ├─ already delivered with this key? don't send
                └─ POST {OUTBOX_URL}/v1/messages
                      Idempotency-Key: invoice-{id}-{Y-m-d}
                      {"recipient": <client phone>, "body": <text>}
                        ├─ 202/200 → sent
                        ├─ 409     → already_sent (the gateway already has the key)
                        ├─ 5xx or timeout → retry_later, the job throws and the queue retries
                        └─ other 4xx → rejected, the job fails without retry
              each attempt becomes a row in the reminders table
```

What stops the duplicate, in layers:

1. **Unique lock** per invoice and day: running the command twice does not queue the same reminder twice.
2. **`retry_after` (90s) greater than the job `timeout` (30s)**, with the HTTP timeout (10s) below both. That is exactly what caused the duplicate I saw: with a smaller `retry_after`, the queue hands the job to another worker while the first one is still waiting for the response. A test reads the config and compares.
3. **The key uses the day the job was queued**, not the day it ran. A retry after midnight reuses the key and the gateway drops it.
4. **Local log**: if there is already a `sent` or `already_sent` row for the key, the job does not even call the gateway.
5. **The gateway** drops resends by `Idempotency-Key`. It is the last barrier for a crash between the gateway accepting and the log being written.

`OUTBOX_URL` is configurable. With the gateway down the send fails with a connection error, becomes `retry_later` and the job goes back to the queue. The compose file has an optional profile that builds the gateway and the provider mock straight from GitHub.

I ran this by hand with the `outbox` profile on: the command marked 6 seeded invoices as overdue and all 6 went out with 202. Running the command again on the same day did not create any new message in the gateway (still 6). With the gateway stopped, one invoice's reminder became `retry_later` with `Could not resolve host: outbox-gateway`; I brought the gateway back and on the next retry, 60s later, it went out with 202.

## Tests

| Guarantee | Test |
|---|---|
| A user only sees their own company's data | `tests/Feature/TenancyTest.php` › só enxerga dados da empresa do usuário logado |
| Without a logged-in user the scope returns nothing | `TenancyTest` › sem usuário logado a consulta não devolve nada |
| `company_id` comes from the user and ignores the payload | `TenancyTest` › preenche company_id com a empresa do usuário logado e ignora outra |
| Every model with `company_id` uses the scope | `TenancyTest` › todo model com company_id usa o escopo de tenant |
| Web IDOR: viewing, editing, paying, deleting and listing another company's client, invoice or reminder is a 404 and nothing changes | `tests/Feature/IdorTest.php` › web: ... |
| API IDOR: same through the API with a Sanctum token, with a positive control on the user's own resource | `IdorTest` › api › ... |
| An invoice cannot point to another company's client | `IdorTest` › não cria fatura apontando pra cliente de outra empresa; `tests/Unit/Application/BillingUseCasesTest.php` › não cria nem move fatura pra cliente de outra empresa |
| Invoice status transitions | `tests/Unit/Domain/InvoiceTest.php` |
| Integer cents, BRL | `tests/Unit/Domain/ValueObjectsTest.php`, `InvoicesTest` › cria fatura em centavos e BRL, `tests/js/lib/money.test.ts` |
| Domain and application do not depend on the framework | `tests/Unit/ArchTest.php` |
| `openapi.yaml` has exactly the API routes | `tests/Feature/OpenApiTest.php` |
| Send with `Idempotency-Key` and the expected JSON | `tests/Feature/RemindersTest.php` › manda pro outbox gateway com Idempotency-Key e o JSON esperado |
| 202/200 ok, 409 already sent, 5xx and timeout retry, other 4xx fail | `RemindersTest` › mapeia a resposta do gateway; timeout ou gateway fora do ar vira retry; 5xx faz o job lançar exceção; 4xx falha o job sem retry |
| No resend on the same day | `RemindersTest` › 409 conta como já enviado e não roda de novo; `RemindersUseCasesTest` › não reenvia no mesmo dia depois de entregue |
| A retry on the next day keeps the original key | `RemindersTest` › retry no dia seguinte mantém a chave do dia em q foi enfileirado |
| Unique lock per invoice and day | `RemindersTest` › lock único por fatura e por dia segura o job duplicado na fila |
| `retry_after` > job `timeout` > HTTP timeout | `RemindersTest` › retry_after de toda conexão de fila é maior q o timeout do job |
| Command scheduled at 9am | `RemindersTest` › comando está agendado todo dia às 9h |
| React pages (dashboard, clients, invoices, reminders) | `tests/js/**` with Vitest + Testing Library |

Test names are in Portuguese. PHP tests run against real Postgres (`invoicing_test`), never SQLite.

### Coverage

- **PHP:** Pest with pcov and `--coverage --min=100` over `app/`. No file is excluded: what is left from the starter kit (providers, middlewares, the `User` model) is covered too. The base `Controller` has no executable lines.
- **Front end:** Vitest with a 100% threshold on lines, branches, functions and statements for the code written here: `resources/js/pages/{dashboard,clients/*,invoices/*,reminders/*}.tsx`, `resources/js/components/pagination.tsx` and `resources/js/lib/{money,invoices,navigation}.ts`.
  Left out is what came with the starter kit: `components/ui/*` (shadcn primitives), `components/{alert-error,app-content,app-logo,app-logo-icon,app-shell,app-sidebar-header,breadcrumbs,heading,input-error,nav-main,nav-user,text-link,user-info}.tsx`, `hooks/*`, `layouts/*`, `lib/utils.ts`, `app.tsx` and the Wayfinder output (`actions/`, `routes/`, `wayfinder/`). Some kit files I only touched to remove what I don't use or to translate text, with no new logic: `pages/auth/login.tsx`, `components/{app-sidebar,user-menu-content,password-input}.tsx`, `types/*`. Those are outside the threshold as well.

### Mutation check

`scripts/mutation-check.sh` breaks each protection on purpose, runs the whole suite and restores the file. Real output from the last run:

```
PEGO   sem escopo de tenant ->  Tests:    18 failed, 111 passed (425 assertions)
PEGO   escopo aberto sem usuário ->  Tests:    1 failed, 128 passed (462 assertions)
PEGO   sem lock único no job ->  Tests:    1 failed, 128 passed (461 assertions)
PEGO   envio sem Idempotency-Key ->  Tests:    2 failed, 127 passed (462 assertions)
PEGO   4xx tratado como retry ->  Tests:    3 failed, 126 passed (459 assertions)
PEGO   retry_after menor q o timeout ->  Tests:    1 failed, 128 passed (461 assertions)
```

(`PEGO` = caught.) The mutations are: no tenant scope, scope open when there is no user, no unique lock on the job, send without `Idempotency-Key`, 4xx treated as retry, `retry_after` below the job timeout. Every mutation made at least one test fail.

## Known limits

- No sign-up or invites: companies and users come from the seed. I also removed password reset, email verification, 2FA and passkeys from the starter kit.
- In practice only BRL. `Money` accepts another currency, but the UI and form assume reais.
- Fixed timezone `America/Sao_Paulo` (`APP_TIMEZONE`), which defines what "overdue today" means.
- The key is per invoice and per day. If someone edits the invoice after that day's reminder, the local log already has the key and nothing goes out; even if it reached the gateway, it would answer 409 (same key, different content), which counts as already sent. The reminder with the corrected amount only goes out the next day.
- Fixed Portuguese reminder text, no opt-out and no approved WhatsApp template. The demo gateway delivers to a mock.
- The unique lock uses the cache (`database` driver in compose). If the cache is cleared while a job is queued, the lock is gone, and the local log and the gateway key are what is left.
- An overdue invoice can go back to "open" through editing (e.g. a renegotiated due date). If the date is still in the past, the next day's command marks it again.
- On phones, tables scroll horizontally inside their card.
- In the front-end tests Inertia's `Head`, `Link` and `usePage` are replaced with simple versions; `useForm` and `router` are the real ones, only spied on.

## How to run

You only need Docker.

```bash
docker compose up -d --build        # app, Postgres 17, queue worker, scheduler and the Vite build
docker compose exec app php artisan migrate:fresh --seed
```

The code is bind-mounted into the container and the image runs as UID/GID 1000 by default. If your user has different ids, pass them at build time (bash defines `UID` without exporting it and zsh treats it as special, so pass it with `env` on the command line):

```bash
env UID=$(id -u) GID=$(id -g) docker compose up -d --build
```

Open http://localhost:8000. Seeded users, password `password` for all:

| Email | Company | Role |
|---|---|---|
| dona@acme.test | Acme | owner |
| membro@acme.test | Acme | member (cannot delete) |
| dono@globex.test | Globex | owner |

Log in as Acme and as Globex to see that neither sees the other.

Reminders:

```bash
docker compose --profile outbox up -d                       # optional: starts outbox-gateway-demo and its mock
docker compose exec app php artisan invoices:mark-overdue   # the scheduler already runs it daily at 9am
```

The `outbox` profile uses the `outbox` database created by the Postgres init script. If the volume existed before, create it with `docker compose exec db createdb -U invoicing outbox`.

API:

```bash
curl -s -X POST localhost:8000/api/v1/tokens -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"dona@acme.test","password":"password","device_name":"cli"}'
curl -s localhost:8000/api/v1/invoices -H "Authorization: Bearer <token>" -H 'Accept: application/json'
```

The contract is in [`openapi.yaml`](openapi.yaml).

Quality checks, all inside the container:

```bash
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app vendor/bin/pest --coverage --min=100
docker compose exec app sh -c 'php artisan wayfinder:generate --with-form && npm run lint && npm run types:check && npm run test:coverage && npm run build'
docker compose exec app sh scripts/mutation-check.sh
```

CI (`.github/workflows/ci.yml`) runs the same steps with a Postgres service.

## License

MIT, see [LICENSE](LICENSE).
