# multitenant-invoicing

[![ci](https://github.com/MiguelSanzBr/multitenant-invoicing/actions/workflows/ci.yml/badge.svg)](https://github.com/MiguelSanzBr/multitenant-invoicing/actions/workflows/ci.yml)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20)
![cobertura 100%](https://img.shields.io/badge/cobertura-100%25-brightgreen)
[![licença MIT](https://img.shields.io/badge/licen%C3%A7a-MIT-blue)](LICENSE)

SaaS pequeno de faturamento multi-tenant em Laravel 13 + Inertia + React. Cada empresa tem seus usuários (dono ou membro), clientes e faturas em BRL. Todo dia um comando marca as faturas vencidas e manda um lembrete por WhatsApp pelo [outbox-gateway-demo](https://github.com/MiguelSanzBr/outbox-gateway-demo), meu outro projeto.

[English version](README.en.md)

![Lista de faturas com status por cor](docs/invoices.png)

**O problema.** Num SaaS com várias empresas no mesmo banco, os dois bugs q mais doem são uma empresa ver dado da outra e o cliente receber a mesma cobrança duas vezes. Eu vi os dois em produção. Esse repo é o desenho q eu faria hoje pra nenhum dos dois acontecer, com teste quebrando se alguém tirar a proteção.

**O q ele garante:**

- Usuário só enxerga a própria empresa, na web e na API. Id de outra empresa dá 404, igual a id q não existe.
- O banco também segura: fatura não aponta pra cliente de outra empresa, lembrete não aponta pra fatura de outra empresa (FK composta).
- Um lembrete por fatura por dia, mesmo com retry de fila, comando rodando duas vezes ou worker morrendo no meio do envio.
- Pagamento feito enquanto o comando diário roda não é desfeito (UPDATE condicional e locking otimista).
- Canal de SMS com teto: no máximo 3 lembretes por fatura e cota diária por empresa.

## Como rodar

Precisa só de Docker.

```bash
docker compose up -d --build   # app, Postgres 18, fila, scheduler, build do Vite e seed da demo
```

Abre http://localhost:8000. A tela de login lista os usuários de demo (senha `password` pra todos):

| E-mail | Empresa | Papel |
|---|---|---|
| dona@acme.test | Acme | dona |
| membro@acme.test | Acme | membro (não exclui) |
| dono@globex.test | Globex | dono |

Entra com a Acme e com a Globex pra ver q uma não enxerga a outra. Detalhes (UID/GID, lembretes, API, imagem de produção) em [Rodando em detalhe](#rodando-em-detalhe).

| Login com os usuários de demo | Painel | Lembretes |
|---|---|---|
| ![Login](docs/login.png) | ![Painel](docs/dashboard.png) | ![Lembretes](docs/reminders.png) |

## Por que esse repo existe

Ele leva pra código público duas lições q tive no trabalho:

1. **Vazamento entre tenants do tipo IDOR num CRM.** Bastava trocar o id na URL pra ver dado de outra empresa. Aqui o isolamento não depende de cada controller lembrar de filtrar: o filtro mora no model, falha fechado e o banco confere de novo.
2. **Envio duplicado causado por retry de fila.** O job demorou, a fila achou q ele tinha morrido, entregou de novo e a mensagem saiu duas vezes. Aqui o job é único por fatura e por dia, o `retry_after` da fila é maior q o `timeout` do job e o envio leva uma chave de idempotência pro gateway descartar repetição.

As duas coisas são resolvidas no desenho e cada uma tem teste q quebra se alguém tirar a proteção (ver [Checagem de mutação](#checagem-de-mutação)).

## Arquitetura

Hexagonal no jeito Laravel: o domínio é PHP puro no centro, o framework fica nas bordas.

```
                 ┌──────────────────────────── Http / Console (adapters de entrada) ─────────────┐
                 │  Controllers web (Inertia)  ·  API /api/v1 (Sanctum)  ·  invoices:mark-overdue │
                 └───────────────┬───────────────────────────────────────────────┬───────────────┘
                                 │ escrita                                       │ leitura
                                 ▼                                               │ (Eloquent com escopo
                 ┌─────────── Application (casos de uso) ───────────┐            │  de tenant, saída
                 │ CreateInvoice · UpdateInvoice · MarkInvoicePaid  │            │  pelos Resources)
                 │ DeleteInvoice · CreateClient · UpdateClient      │            │
                 │ DeleteClient · MarkOverdueInvoices               │            │
                 │ SendInvoiceReminder                              │            │
                 └───────────────┬──────────────────────────────────┘            │
                                 ▼                                               │
                 ┌──────────────── Domain (PHP puro) ───────────────┐            │
                 │ Invoice (draft→open→paid/overdue) · Client       │            │
                 │ Money · DueDate · PhoneNumber · IdempotencyKey   │            │
                 │ ReminderLimits                                   │            │
                 │ portas: InvoiceRepository · ClientRepository     │            │
                 │ CrossTenantInvoices · CrossTenantClients         │            │
                 │ ReminderSender · ReminderLog · ReminderQueue     │            │
                 │ TenantContext                                    │            │
                 └───────────────▲──────────────────────────────────┘            │
                                 │ implementa                                    ▼
                 ┌──────────────── Infrastructure (adapters de saída) ───────────────────────────┐
                 │ Models Eloquent + escopo de tenant · repositórios Eloquent                    │
                 │ OutboxReminderSender (HTTP) · jobs da fila · AuthTenantContext                 │
                 └────────────────────────────────────────────────────────────────────────────────┘
```

- `app/Domain`: entidades, value objects e interfaces. Nenhum `Illuminate`, `Carbon` ou outra camada. A fatura só muda de status por método (`open`, `revertToDraft`, `markPaid`, `markOverdue`) e recusa transição inválida com `RuleViolation`. A edição mexe só nos dados. Value object com valor inválido lança `InvalidValue` (422).
- `app/Application`: um caso de uso por classe, dependendo só das portas do domínio.
- `app/Infrastructure`: Eloquent, fila, HTTP e auth. O escopo global de tenant fica aqui pq é detalhe de persistência.
- `app/Http` e `app/Console`: controllers, policies, form requests, resources e o comando diário. Toda escrita passa por caso de uso.

A regra de dependência é verificada por teste de arquitetura do Pest (`tests/Unit/ArchTest.php`): domínio e aplicação sem framework, `Http` sem usar adapter de saída direto, `declare(strict_types=1)` em todo arquivo, classes do domínio finais e só o comando diário e o job de lembrete usando as portas q atravessam empresas.

**Por que assim.** As regras q importam (transição de status, valor em centavos, chave de idempotência, o q fazer com cada resposta do gateway) ficam testáveis sem banco nem HTTP, com repositório em memória. O Laravel continua fazendo o q faz bem: rota, validação, fila, Eloquent.

**Onde fui pragmático.** Leitura (listas, painel, histórico) vai direto no Eloquent dentro do controller, com o escopo de tenant aplicado, e sai pelos mesmos Resources da API. Criar um caso de uso e um DTO pra cada listagem seria cerimônia sem regra nenhuma dentro. Policies continuam como autorização de "quem pode" (mesma empresa, dono pra excluir) e o domínio responde "pode nesse estado?".

## Isolamento por tenant

- `BelongsToCompany` (trait) + `CompanyScope` (escopo global) em todo model com `company_id`: `Client`, `Invoice` e `Reminder`.
- Com usuário logado a consulta filtra pela empresa dele. **Sem usuário o escopo devolve vazio**, não tudo.
- Job e comando q precisam atravessar empresas usam portas separadas (`CrossTenantInvoices`, `CrossTenantClients`). Um arch test trava quem pode usar: só `MarkOverdueInvoices`, `SendInvoiceReminder` e os adapters. Caso de uso de tela não recebe isso nem por engano.
- Ao criar, o `company_id` vem sempre do usuário logado e o valor do payload é ignorado. Os models de tenant não têm `fillable`: escrita só pelo repositório.
- Route model binding passa pelo escopo, então id de outra empresa vira 404, igual a id inexistente.
- `client_id` da fatura é validado contra a empresa do usuário no form request e de novo no caso de uso.
- No banco: `unique(company_id, id)` e FK composta `invoices(company_id, client_id)` e `reminders(company_id, invoice_id)`, mais CHECK de valor positivo, status válido e `paid_at` só quando paga. O job de lembrete ainda confere se cliente e fatura são da mesma empresa antes de mandar o SMS.
- Policies em toda ação, web e API, com teste de cada uma pelas duas portas.
- Um teste percorre `app/Infrastructure/Persistence/Models` e falha se algum model com `company_id` não usar o escopo. `User` é a única exceção, pq o login procura o usuário antes de existir tenant.
- As props do Inertia saem pelos Resources: `company_id`, timestamps e coluna nova não vão pro HTML por padrão.

## Lembretes idempotentes e o outbox-gateway-demo

```
09:00 invoices:mark-overdue (onOneServer, mutex de 60 min)
          │
          ├─ UPDATE invoices SET status='overdue' WHERE status='open' AND due_date < hoje   (um comando só)
          │
          └─ ids das vencidas em blocos de 500 (lazyById) → QueueInvoiceRemindersJob por bloco
                  └─ SendInvoiceReminderJob(invoice_id, dia) por fatura
                        ShouldBeUnique, uniqueId = invoice-{id}-{Y-m-d}
                        timeout 30s, retry_after da fila 90s, backoff 60/300/900/3600s, prazo de 12h
                        disjuntor: 10 falhas seguidas e os próximos voltam pra fila por 5 min sem chamar
                        │
                        ▼
              SendInvoiceReminder (caso de uso)
                ├─ fatura não está mais overdue? não manda
                ├─ já tem envio entregue com essa chave? não manda
                ├─ fatura já recebeu 3 lembretes ou a empresa estourou a cota do dia? não manda
                ├─ cliente de outra empresa? falha o job, sem SMS
                └─ POST {OUTBOX_URL}/v1/messages
                      Idempotency-Key: {app}-{ambiente}-invoice-{id}-{Y-m-d}
                      {"recipient": <telefone do cliente>, "body": <texto>}
                        ├─ 202/200 → sent
                        ├─ 409     → already_sent (o gateway já tem a chave)
                        ├─ 5xx, 408, 429 ou sem resposta → retry_later, volta pra fila (respeita o Retry-After)
                        └─ outro 4xx → rejected, o job falha sem retry
              cada tentativa vira uma linha na tabela reminders
```

O q segura a duplicata, em camadas:

1. **Lock único** por fatura e por dia: rodar o comando duas vezes não enfileira o mesmo lembrete duas vezes.
2. **`retry_after` (90s) maior q o `timeout` do job (30s)**, e o timeout HTTP (10s, conexão em 2s) menor q os dois. Foi exatamente o q causou a duplicata q eu vi: com `retry_after` menor, a fila entrega o job pra outro worker enquanto o primeiro ainda espera a resposta. Tem teste lendo a config e comparando, e outro conferindo q o client HTTP usa esses timeouts.
3. **Chave do dia em q foi enfileirado**, não do dia em q rodou. Um retry de madrugada reusa a chave e o gateway descarta. No gateway a chave leva app e ambiente (`OUTBOX_KEY_PREFIX`), pra staging e produção no mesmo outbox não colidirem.
4. **Log local**: se já tem `sent` ou `already_sent` pra chave, o job nem chama o gateway. No banco, um índice único parcial garante no máximo um `sent` por chave.
5. **O gateway** descarta reenvio pela `Idempotency-Key`. É a última barreira pro caso de crash entre o gateway aceitar e o log ser gravado.

O erro cru do gateway (host interno, corpo da resposta) vai só pro log do servidor. O histórico de lembretes do tenant mostra um resumo tipo "Gateway respondeu HTTP 422.".

Sem o gateway no ar o envio dá erro de conexão, vira `retry_later` e o job volta pra fila. A compose tem um profile opcional q sobe o gateway e o mock de provedor, fixados num commit do outro repo.

## Testes

| Garantia | Teste |
|---|---|
| Usuário só enxerga dados da própria empresa; sem usuário o escopo devolve vazio | `tests/Feature/TenancyTest.php` |
| `company_id` vem do usuário e ignora o payload; models de tenant recusam mass assignment | `TenancyTest` |
| Todo model com `company_id` usa o escopo | `TenancyTest` › todo model com company_id usa o escopo de tenant |
| IDOR web e API: ver, editar, pagar e apagar o q é de outra empresa dá 404 e nada muda; listas só da própria empresa (q também tem dados no teste) | `tests/Feature/IdorTest.php` |
| Quem pode o q, em cada ação, pela web e pela API | `tests/Feature/PolicyTest.php` |
| Banco recusa fatura e lembrete cruzando empresa, valor zero, status inventado e dois `sent` na mesma chave | `tests/Feature/SchemaTest.php` |
| Transições de status explícitas, edição não mexe em status, vencida renegociada volta pra em aberto | `tests/Unit/Domain/InvoiceTest.php`, `tests/Unit/Application/BillingUseCasesTest.php` |
| Pagamento entre carregar e salvar não é desfeito (locking otimista) | `tests/Feature/InvoicesTest.php` |
| Comando marca vencidas num UPDATE só e enfileira em blocos | `tests/Feature/RemindersTest.php` |
| Envio com `Idempotency-Key` com prefixo, JSON esperado, timeouts da config | `RemindersTest` |
| 202/200 ok, 409 já enviado, 5xx/408/429/timeout retry com Retry-After, outro 4xx falha | `RemindersTest` |
| Fila `database` de verdade: backoff em `available_at`, lock liberado, `failed_jobs`, disjuntor e prazo de 12h | `tests/Feature/ReminderQueueTest.php` |
| Fake do log de lembretes se comporta igual ao Eloquent | `tests/Feature/ReminderLogContractTest.php` |
| Teto de 3 lembretes por fatura e cota diária por empresa | `tests/Unit/Application/RemindersUseCasesTest.php` |
| Token só leitura por padrão, abilities por rota, listagem, revogação, hash fake pra e-mail inexistente e limites | `tests/Feature/TokenTest.php` |
| Headers de segurança, seed recusando produção, props do Inertia pelos Resources | `SecurityHeadersTest`, `SeedAndLocaleTest`, `InertiaPropsTest` |
| Arquitetura (camadas, portas, strict types) e `openapi.yaml` igual às rotas | `tests/Unit/ArchTest.php`, `tests/Feature/OpenApiTest.php` |
| Páginas React (login, painel, clientes, faturas, lembretes) | `tests/js/**` com Vitest + Testing Library |

São 275 testes PHP (1045 asserções) contra Postgres de verdade (`invoicing_test`), nunca SQLite, e 44 testes de front. O phpunit falha em warning.

### Cobertura

- **PHP:** Pest com pcov e `--coverage --min=100` sobre `app/`. Nenhum arquivo excluído.
- **Front:** Vitest com threshold de 100% em linhas, branches, funções e statements sobre `resources/js` inteiro. Fica de fora só o q o Wayfinder escreve (`actions/`, `routes/`, `wayfinder/`) e o q veio pronto do starter kit: `components/ui/*`, `layouts/*`, `hooks/*`, `types/*`, `lib/utils.ts`, `app.tsx` e os componentes de casca (sidebar, menu do usuário, breadcrumbs, logo). Arquivo novo entra no gate sozinho.

### Checagem de mutação

Duas camadas no job `mutation` do CI:

1. `scripts/mutation-check.sh` quebra cada proteção principal de propósito (inclusive config e `implements ShouldBeUnique`, q ferramenta nenhuma muta), roda a suíte inteira e restaura o arquivo, mesmo se for interrompido. Saída real da última rodada:

```
PEGO   sem escopo de tenant ->  Tests:    17 failed, 258 passed (991 assertions)
PEGO   escopo aberto sem usuário ->  Tests:    1 failed, 274 passed (1044 assertions)
PEGO   sem lock único no job ->  Tests:    1 failed, 274 passed (1043 assertions)
PEGO   envio sem Idempotency-Key ->  Tests:    2 failed, 273 passed (1044 assertions)
PEGO   4xx tratado como retry ->  Tests:    7 failed, 268 passed (1034 assertions)
PEGO   retry_after menor q o timeout ->  Tests:    1 failed, 274 passed (1043 assertions)
```

2. `pest --mutate` em `Domain`, `Application` e `Infrastructure` com `--min=97`. Começou em 83,4% (37 mutantes escapando). Matei os q tinham sentido (limite de borda, Retry-After no passado, janela do dia da cota, gravação sem usuário logado...) e hoje está em **98,09%** (308 de 314 pegos, 2 deles por timeout). Os 6 q sobram são equivalentes: cast redundante do Eloquent e um caminho em q o fallback de backoff do worker dá o mesmo resultado.

## Limites conhecidos

- A compose é pra desenvolvimento: bind mount, `artisan serve`, senha fraca no Postgres (só em 127.0.0.1). Pra produção existe o target `prod` do `docker/Dockerfile` (FrankenPHP, opcache, sem root, healthcheck), mas não tem deploy pronto: banco, segredos e HTTPS ficam com quem for subir. Em produção use `DB_PASSWORD` forte e `SESSION_SECURE_COOKIE=true`.
- Não tem cadastro nem convite de usuário: empresas e usuários vêm do seed. Também tirei do starter kit reset de senha, verificação de e-mail, 2FA e passkeys. Sem troca de senha, não tem como revogar token por isso; o usuário revoga pelo `DELETE /api/v1/tokens/current` e o token expira em 30 dias.
- Fatura paga pode ser excluída pelo dono. Não tem soft delete nem trilha de auditoria, então o histórico some junto (inclusive os lembretes dela).
- Na prática só BRL. O `Money` aceita outra moeda e a edição preserva a moeda da fatura, mas a UI e o form assumem real.
- Fuso fixo em `America/Sao_Paulo` (`APP_TIMEZONE`), q define o q é "vencido hoje". A cota diária por empresa conta pelo horário em q o lembrete saiu.
- A chave é por fatura e por dia. Se alguém editar a fatura depois do lembrete do dia, o log local já tem a chave e nada sai; mesmo se chegasse no gateway, ele responderia 409 (mesma chave com outro conteúdo), q conta como já enviado. O lembrete com o valor corrigido só sai no dia seguinte.
- Lembrete com texto em português e a descrição livre da fatura, sem opt-out e sem template aprovado de WhatsApp. O teto por fatura e a cota por empresa limitam o abuso, mas não filtram link no texto. O gateway de demo entrega pra um mock.
- O lock único usa o cache (driver `database` na compose). Se o cache for limpo com job na fila, o lock some, e aí sobram o log local, o índice único de `sent` e a chave no gateway.
- Vencida volta pra "em aberto" quando o vencimento é renegociado pra hoje ou depois. Com a data ainda no passado ela continua vencida; pra rascunho ela não volta.
- CSP só com `frame-ancestors`: o script inline do tema e o Vite pedem um passo a mais pra uma política completa.
- No celular as tabelas rolam na horizontal dentro do card.
- Nos testes do front o `Head`, o `Link` e o `usePage` do Inertia são substituídos por versões simples; `useForm` e `router` são os reais, só espionados.

## Rodando em detalhe

O código entra no container por bind mount e a imagem roda com UID/GID 1000 por padrão. Se o seu usuário for outro, passa os ids no build (no bash o `UID` existe mas não é exportado e no zsh ele é especial, por isso vai com `env` na linha do comando):

```bash
env UID=$(id -u) GID=$(id -g) docker compose up -d --build
```

Na primeira subida o `setup` migra e semeia a demo; depois só migra. Pra recomeçar do zero: `docker compose exec app php artisan migrate:fresh --seed`.

O Postgres fica no host só em `127.0.0.1:5434` (não 5432, pra não brigar com outro Postgres local): `psql -h localhost -p 5434 -U invoicing invoicing`, senha `invoicing`. As portas mudam com `APP_PORT` e `DB_PORT_HOST`.

O volume `pgdata` de antes do Postgres 18 não sobe (o 18 mudou o diretório dos dados e o formato não é compatível com o 17). Como é só a demo, recria com `docker compose down -v` e sobe de novo; se tiver algo ali q importa, faz `pg_dump` no 17 antes e restaura no 18.

Lembretes:

```bash
docker compose --profile outbox up -d                       # opcional: sobe o outbox-gateway-demo e o mock
docker compose exec app php artisan invoices:mark-overdue   # o scheduler já roda todo dia às 9h
```

O profile `outbox` usa o banco `outbox` criado pelo script de init do Postgres. Se o volume já existia antes, cria com `docker compose exec db createdb -U invoicing outbox`.

API (o token sai só leitura; pra escrever, pede `"abilities": ["invoices:write"]`):

```bash
TOKEN=$(curl -s -X POST localhost:8000/api/v1/tokens -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"email":"dona@acme.test","password":"password","device_name":"cli"}' | sed -E 's/.*"token":"([^"]+)".*/\1/')
curl -s localhost:8000/api/v1/invoices -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
curl -s -X DELETE localhost:8000/api/v1/tokens/current -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json'
```

O token vale 30 dias (`SANCTUM_EXPIRATION`, em minutos), tem prefixo `mti_` pro secret scanning do GitHub e o expirado é limpo todo dia. A API autenticada aceita 60 pedidos por minuto por usuário; a emissão de token, 6 por minuto por IP e por e-mail. O contrato com exemplos está em [`openapi.yaml`](openapi.yaml) ([abrir no Swagger Editor](https://editor.swagger.io/?url=https://raw.githubusercontent.com/MiguelSanzBr/multitenant-invoicing/main/openapi.yaml)).

Imagem de produção:

```bash
docker build -f docker/Dockerfile --target prod -t multitenant-invoicing:prod .
docker run -p 8080:8080 -e APP_KEY=base64:... -e DB_HOST=... -e DB_PASSWORD=... multitenant-invoicing:prod
```

Qualidade, tudo dentro do container:

```bash
docker compose exec app vendor/bin/pint --test
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app vendor/bin/pest --coverage --min=100
docker compose exec app vendor/bin/pest --mutate --path=app/Domain,app/Application,app/Infrastructure --covered-only --parallel --min=97
docker compose exec app sh scripts/mutation-check.sh
docker compose exec app sh -c 'php artisan wayfinder:generate --with-form && npm run lint && npm run types:check && npm run test:coverage && npm run build'
```

Ponta a ponta, com a stack inteira e o outbox-gateway-demo de verdade (login, API com e sem token, token só leitura sem escrever, lembrete chegando no gateway com a chave certa e segunda rodada no mesmo dia sem duplicar). Usa outro nome de projeto do compose e outras portas (18000 e 15434), então roda com a stack de dev no ar sem mexer no seu banco. Se falhar, os logs de todos os serviços vão pra `e2e-logs.txt`:

```bash
bash scripts/e2e.sh
```

O CI (`.github/workflows/ci.yml`) roda tudo isso em 5 jobs: `backend` (Pint, Larastan nível max, Pest com 100%), `frontend` (ESLint, tipos, Vitest com 100%, build), `mutation` (as 6 proteções e o `pest --mutate` com mínimo de 97%), `image` (builda o target `prod` e espera o healthcheck) e `e2e` (o `scripts/e2e.sh`, com os logs como artifact quando falha). Actions fixadas por SHA, runner fixo em `ubuntu-24.04` e Dependabot pra actions, composer, npm e imagens Docker.

## Licença

MIT, ver [LICENSE](LICENSE).
