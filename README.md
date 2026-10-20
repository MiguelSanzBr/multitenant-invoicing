# multitenant-invoicing

SaaS pequeno de faturamento multi-tenant em Laravel 13 + Inertia + React. Cada empresa tem seus usuários (dono ou membro), clientes e faturas em BRL. Todo dia um comando marca as faturas vencidas e manda um lembrete por WhatsApp pelo [outbox-gateway-demo](https://github.com/MiguelSanzBr/outbox-gateway-demo), meu outro projeto.

[English version](README.en.md)

## Por que esse repo existe

Ele leva pra código público duas lições q tive no trabalho:

1. **Vazamento entre tenants do tipo IDOR num CRM.** Bastava trocar o id na URL pra ver dado de outra empresa. Aqui o isolamento não depende de cada controller lembrar de filtrar: o filtro mora no model e falha fechado.
2. **Envio duplicado causado por retry de fila.** O job demorou, a fila achou q ele tinha morrido, entregou de novo e a mensagem saiu duas vezes. Aqui o job é único por fatura e por dia, o `retry_after` da fila é maior q o `timeout` do job e o envio leva uma chave de idempotência pro gateway descartar repetição.

As duas coisas são resolvidas no desenho e cada uma tem teste q quebra se alguém tirar a proteção (ver [Checagem de mutação](#checagem-de-mutação)).

## Arquitetura

Hexagonal no jeito Laravel: o domínio é PHP puro no centro, o framework fica nas bordas.

```
                 ┌──────────────────────────── Http / Console (adapters de entrada) ─────────────┐
                 │  Controllers web (Inertia)  ·  API /api/v1 (Sanctum)  ·  invoices:mark-overdue │
                 └───────────────┬───────────────────────────────────────────────┬───────────────┘
                                 │ escrita                                       │ leitura
                                 ▼                                               │ (direto no Eloquent,
                 ┌─────────── Application (casos de uso) ───────────┐            │  com escopo de tenant)
                 │ CreateInvoice · UpdateInvoice · MarkInvoicePaid  │            │
                 │ DeleteInvoice · SaveClient · DeleteClient        │            │
                 │ MarkOverdueInvoices · SendInvoiceReminder        │            │
                 └───────────────┬──────────────────────────────────┘            │
                                 ▼                                               │
                 ┌──────────────── Domain (PHP puro) ───────────────┐            │
                 │ Invoice (draft→open→paid/overdue) · Client       │            │
                 │ Money · DueDate · PhoneNumber · IdempotencyKey   │            │
                 │ portas: InvoiceRepository · ClientRepository     │            │
                 │ ReminderSender · ReminderLog · ReminderQueue     │            │
                 │ TenantContext                                    │            │
                 └───────────────▲──────────────────────────────────┘            │
                                 │ implementa                                    ▼
                 ┌──────────────── Infrastructure (adapters de saída) ───────────────────────────┐
                 │ Models Eloquent + escopo de tenant · repositórios Eloquent                    │
                 │ OutboxReminderSender (HTTP) · SendInvoiceReminderJob (fila) · AuthTenantContext │
                 └────────────────────────────────────────────────────────────────────────────────┘
```

- `app/Domain`: entidades, value objects e interfaces. Nenhum `Illuminate`, `Carbon` ou outra camada. A fatura só muda de status por método (`open`, `markPaid`, `markOverdue`, `edit`) e recusa transição inválida com `RuleViolation`.
- `app/Application`: um caso de uso por classe, dependendo só das portas do domínio.
- `app/Infrastructure`: Eloquent, fila, HTTP e auth. O escopo global de tenant fica aqui pq é detalhe de persistência.
- `app/Http` e `app/Console`: controllers, policies, form requests, resources e o comando diário. Toda escrita passa por caso de uso.

A regra de dependência é verificada por teste de arquitetura do Pest (`tests/Unit/ArchTest.php`), q roda junto com a suíte no CI.

**Por que assim.** As regras q importam (transição de status, valor em centavos, chave de idempotência, o q fazer com cada resposta do gateway) ficam testáveis sem banco nem HTTP, com repositório em memória. O Laravel continua fazendo o q faz bem: rota, validação, fila, Eloquent.

**Onde fui pragmático.** Leitura (listas, painel, histórico) vai direto no Eloquent dentro do controller, com o escopo de tenant aplicado. Criar um caso de uso e um DTO pra cada listagem seria cerimônia sem regra nenhuma dentro. Policies continuam como autorização de "quem pode" (mesma empresa, dono pra excluir) e o domínio responde "pode nesse estado?".

## Isolamento por tenant

- `BelongsToCompany` (trait) + `CompanyScope` (escopo global) em todo model com `company_id`: `Client`, `Invoice` e `Reminder`.
- Com usuário logado a consulta filtra pela empresa dele. **Sem usuário o escopo devolve vazio**, não tudo. Job e comando q precisam atravessar empresas usam métodos com nome explícito (`findAcrossTenants`, `openPastDue`), fáceis de achar num review.
- Ao criar, o `company_id` vem sempre do usuário logado e o valor do payload é ignorado.
- Route model binding passa pelo escopo, então id de outra empresa vira 404, igual a id inexistente. Não dá pra descobrir se o id existe.
- `client_id` da fatura é validado contra a empresa do usuário no form request e de novo no caso de uso.
- Policies em toda ação, web e API.
- Um teste percorre `app/Infrastructure/Persistence/Models` e falha se algum model com `company_id` não usar o escopo. `User` é a única exceção, pq o login procura o usuário antes de existir tenant.

## Lembretes idempotentes e o outbox-gateway-demo

```
09:00 invoices:mark-overdue ── marca open com vencimento no passado como overdue
          │
          └─ pra cada fatura overdue: SendInvoiceReminderJob(invoice_id, dia)
                  ShouldBeUnique, uniqueId = invoice-{id}-{Y-m-d}
                  timeout 30s, retry_after da fila 90s, 5 tentativas com backoff
                        │
                        ▼
              SendInvoiceReminder (caso de uso)
                ├─ fatura não está mais overdue? não manda
                ├─ já tem envio entregue com essa chave? não manda
                └─ POST {OUTBOX_URL}/v1/messages
                      Idempotency-Key: invoice-{id}-{Y-m-d}
                      {"recipient": <telefone do cliente>, "body": <texto>}
                        ├─ 202/200 → sent
                        ├─ 409     → already_sent (o gateway já tem a chave)
                        ├─ 5xx ou timeout → retry_later, o job lança exceção e a fila tenta de novo
                        └─ outro 4xx → rejected, o job falha sem retry
              cada tentativa vira uma linha na tabela reminders
```

O q segura a duplicata, em camadas:

1. **Lock único** por fatura e por dia: rodar o comando duas vezes não enfileira o mesmo lembrete duas vezes.
2. **`retry_after` (90s) maior q o `timeout` do job (30s)**, e o timeout HTTP (10s) menor q os dois. Foi exatamente o q causou a duplicata q eu vi: com `retry_after` menor, a fila entrega o job pra outro worker enquanto o primeiro ainda espera a resposta. Tem teste lendo a config e comparando.
3. **Chave do dia em q foi enfileirado**, não do dia em q rodou. Um retry de madrugada reusa a chave e o gateway descarta.
4. **Log local**: se já tem `sent` ou `already_sent` pra chave, o job nem chama o gateway.
5. **O gateway** descarta reenvio pela `Idempotency-Key`. É a última barreira pro caso de crash entre o gateway aceitar e o log ser gravado.

O `OUTBOX_URL` é configurável. Sem o gateway no ar o envio dá erro de conexão, vira `retry_later` e o job volta pra fila. A compose tem um profile opcional q sobe o gateway e o mock de provedor direto do GitHub.

Rodei isso na mão com o profile `outbox` ligado: o comando marcou 6 faturas do seed como vencidas e as 6 saíram com 202. Rodar o comando de novo no mesmo dia não gerou mensagem nova no gateway (continuou com 6). Com o gateway parado, o lembrete de uma fatura ficou `retry_later` com `Could not resolve host: outbox-gateway`; subi o gateway e no retry seguinte, 60s depois, saiu com 202.

## Testes

| Garantia | Teste |
|---|---|
| Usuário só enxerga dados da própria empresa | `tests/Feature/TenancyTest.php` › só enxerga dados da empresa do usuário logado |
| Sem usuário logado o escopo devolve vazio | `TenancyTest` › sem usuário logado a consulta não devolve nada |
| `company_id` vem do usuário e ignora o payload | `TenancyTest` › preenche company_id com a empresa do usuário logado e ignora outra |
| Todo model com `company_id` usa o escopo | `TenancyTest` › todo model com company_id usa o escopo de tenant |
| IDOR web: ver, editar, pagar, apagar e listar cliente, fatura e lembrete de outra empresa dá 404 e nada muda | `tests/Feature/IdorTest.php` › web: ... |
| IDOR API: o mesmo pela API com token Sanctum, com controle positivo no próprio recurso | `IdorTest` › api › ... |
| Fatura não aponta pra cliente de outra empresa | `IdorTest` › não cria fatura apontando pra cliente de outra empresa; `tests/Unit/Application/BillingUseCasesTest.php` › não cria nem move fatura pra cliente de outra empresa |
| Transições de status da fatura | `tests/Unit/Domain/InvoiceTest.php` |
| Valor em centavos inteiros, BRL | `tests/Unit/Domain/ValueObjectsTest.php`, `InvoicesTest` › cria fatura em centavos e BRL, `tests/js/lib/money.test.ts` |
| Domínio e aplicação não dependem de framework | `tests/Unit/ArchTest.php` |
| `openapi.yaml` tem exatamente as rotas da API | `tests/Feature/OpenApiTest.php` |
| Envio com `Idempotency-Key` e o JSON esperado | `tests/Feature/RemindersTest.php` › manda pro outbox gateway com Idempotency-Key e o JSON esperado |
| 202/200 ok, 409 já enviado, 5xx e timeout retry, outro 4xx falha | `RemindersTest` › mapeia a resposta do gateway; timeout ou gateway fora do ar vira retry; 5xx faz o job lançar exceção; 4xx falha o job sem retry |
| Não reenvia no mesmo dia | `RemindersTest` › 409 conta como já enviado e não roda de novo; `RemindersUseCasesTest` › não reenvia no mesmo dia depois de entregue |
| Retry no dia seguinte mantém a chave original | `RemindersTest` › retry no dia seguinte mantém a chave do dia em q foi enfileirado |
| Lock único por fatura e dia | `RemindersTest` › lock único por fatura e por dia segura o job duplicado na fila |
| `retry_after` > `timeout` do job > timeout HTTP | `RemindersTest` › retry_after de toda conexão de fila é maior q o timeout do job |
| Comando agendado às 9h | `RemindersTest` › comando está agendado todo dia às 9h |
| Páginas React (painel, clientes, faturas, lembretes) | `tests/js/**` com Vitest + Testing Library |

Os testes PHP rodam contra Postgres de verdade (`invoicing_test`), nunca SQLite.

### Cobertura

- **PHP:** Pest com pcov e `--coverage --min=100` sobre `app/`. Nenhum arquivo excluído: o q sobrou do starter kit (providers, middlewares, model `User`) também está coberto. O `Controller` base não tem linha executável.
- **Front:** Vitest com threshold de 100% em linhas, branches, funções e statements para o código escrito aqui: `resources/js/pages/{dashboard,clients/*,invoices/*,reminders/*}.tsx`, `resources/js/components/pagination.tsx` e `resources/js/lib/{money,invoices,navigation}.ts`.
  Fica de fora o q veio do starter kit: `components/ui/*` (primitivos shadcn), `components/{alert-error,app-content,app-logo,app-logo-icon,app-shell,app-sidebar-header,breadcrumbs,heading,input-error,nav-main,nav-user,text-link,user-info}.tsx`, `hooks/*`, `layouts/*`, `lib/utils.ts`, `app.tsx` e os gerados pelo Wayfinder (`actions/`, `routes/`, `wayfinder/`). Alguns arquivos do kit eu mexi só pra remover o q não uso ou traduzir texto, sem lógica nova: `pages/auth/login.tsx`, `components/{app-sidebar,user-menu-content,password-input}.tsx`, `types/*`. Esses também ficam fora do threshold.

### Checagem de mutação

`scripts/mutation-check.sh` quebra cada proteção de propósito, roda a suíte inteira e restaura o arquivo. Saída real da última rodada:

```
PEGO   sem escopo de tenant ->  Tests:    18 failed, 111 passed (425 assertions)
PEGO   escopo aberto sem usuário ->  Tests:    1 failed, 128 passed (462 assertions)
PEGO   sem lock único no job ->  Tests:    1 failed, 128 passed (461 assertions)
PEGO   envio sem Idempotency-Key ->  Tests:    2 failed, 127 passed (462 assertions)
PEGO   4xx tratado como retry ->  Tests:    3 failed, 126 passed (459 assertions)
PEGO   retry_after menor q o timeout ->  Tests:    1 failed, 128 passed (461 assertions)
```

Toda mutação fez pelo menos um teste falhar.

## Limites conhecidos

- Não tem cadastro nem convite de usuário: empresas e usuários vêm do seed. Também tirei do starter kit reset de senha, verificação de e-mail, 2FA e passkeys.
- Na prática só BRL. O `Money` aceita outra moeda, mas a UI e o form assumem real.
- Fuso fixo em `America/Sao_Paulo` (`APP_TIMEZONE`), q define o q é "vencido hoje".
- A chave é por fatura e por dia. Se alguém editar a fatura depois do lembrete do dia, o log local já tem a chave e nada sai; mesmo se chegasse no gateway, ele responderia 409 (mesma chave com outro conteúdo), q conta como já enviado. O lembrete com o valor corrigido só sai no dia seguinte.
- Lembrete com texto fixo em português, sem opt-out e sem template aprovado de WhatsApp. O gateway de demo entrega pra um mock.
- O lock único usa o cache (driver `database` na compose). Se o cache for limpo com job na fila, o lock some, e aí sobram o log local e a chave no gateway.
- Fatura vencida pode voltar pra "em aberto" pela edição (ex. vencimento renegociado). Se a data continuar no passado, o comando do dia seguinte marca de novo.
- No celular as tabelas rolam na horizontal dentro do card.
- Nos testes do front o `Head`, o `Link` e o `usePage` do Inertia são substituídos por versões simples; `useForm` e `router` são os reais, só espionados.

## Como rodar

Precisa só de Docker.

```bash
docker compose up -d --build        # app, Postgres 17, fila, scheduler e build do Vite
docker compose exec app php artisan migrate:fresh --seed
```

Abre http://localhost:8000. Usuários do seed, senha `password` pra todos:

| E-mail | Empresa | Papel |
|---|---|---|
| dona@acme.test | Acme | dono |
| membro@acme.test | Acme | membro (não exclui) |
| dono@globex.test | Globex | dono |

Entra com a Acme e com a Globex pra ver q uma não enxerga a outra.

Lembretes:

```bash
docker compose exec app php artisan invoices:mark-overdue   # o scheduler já roda todo dia às 9h
docker compose --profile outbox up -d                       # opcional: sobe o outbox-gateway-demo e o mock
```

O profile `outbox` usa o banco `outbox` criado pelo script de init do Postgres. Se o volume já existia antes, cria com `docker compose exec db createdb -U invoicing outbox`.

API:

```bash
curl -s -X POST localhost:8000/api/v1/tokens -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"dona@acme.test","password":"password","device_name":"cli"}'
curl -s localhost:8000/api/v1/invoices -H "Authorization: Bearer <token>" -H 'Accept: application/json'
```

O contrato está em [`openapi.yaml`](openapi.yaml).

Qualidade, tudo dentro do container:

```bash
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app vendor/bin/pest --coverage --min=100
docker compose exec app sh -c 'php artisan wayfinder:generate --with-form && npm run lint && npm run types:check && npm run test:coverage && npm run build'
docker compose exec app sh scripts/mutation-check.sh
```

O CI (`.github/workflows/ci.yml`) roda os mesmos passos com um serviço Postgres.

## Licença

MIT, ver [LICENSE](LICENSE).
