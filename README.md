# InsureFlow

Sistema web de gestão para corretoras de seguros: clientes, seguradoras, modalidades,
apólices, coberturas, parcelas, sinistros e dashboard de indicadores.

> **Status:** Fases 1 (fundação), 2 (banco de dados) e 3 (backend e API) concluídas.
> A API REST está completa e testada; as telas Vue dos módulos ainda não foram
> construídas (a SPA exibe apenas a página de verificação do ambiente).

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 13 · PHP 8.4 · Sanctum (SPA, cookie + CSRF) |
| Frontend | Vue 3 (Composition API) · Vue Router · Pinia · Axios · Tailwind CSS 4 · Vite |
| Banco | PostgreSQL 17 |
| Infra | Docker · Docker Compose |

## Arquitetura resumida

Uma única aplicação Laravel expõe a API REST em `/api/*` e entrega uma view que
carrega a SPA Vue (`resources/js`). Qualquer outra URL é resolvida pelo Vue Router.
A autenticação usa o Sanctum em modo SPA: sessão em cookie HttpOnly com proteção
CSRF, sem tokens no `localStorage`.

### Serviços Docker

| Serviço | Função | Porta no host |
|---|---|---|
| `web` | nginx, ponto de entrada HTTP | `8000` |
| `app` | PHP-FPM executando o Laravel | — |
| `scheduler` | `php artisan schedule:work` (tarefas agendadas) | — |
| `postgres` | Banco de dados (`insureflow` e `insureflow_test`) | `5433` |
| `node` | Servidor Vite com HMR | `5173` |

## Requisitos

Somente **Docker** com **Docker Compose v2** (Docker Desktop no Windows/macOS).
Não é necessário ter PHP, Composer, Node ou PostgreSQL instalados na máquina.

## Instalação

```bash
git clone <url-do-repositorio> insureflow
cd insureflow
cp .env.example .env

docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

Acesse **http://localhost:8000**. A página inicial verifica se frontend, API e
banco estão funcionando.

O container `node` executa `npm install` automaticamente ao subir; na primeira
vez pode levar alguns minutos até o Vite ficar disponível
(`docker compose logs -f node`). O `scheduler` reinicia sozinho até que o
`composer install` tenha sido executado.

## Dados de demonstração

`php artisan migrate --seed` (ou `migrate:fresh --seed` para recriar tudo) gera
dados fictícios coerentes, com datas relativas ao dia da execução: cerca de 180
apólices em todos os status, coberturas, parcelas pagas, a vencer e atrasadas,
apólices vencendo nos próximos 30 dias e sinistros com histórico de status.

| Perfil | E-mail |
|---|---|
| Administrador | `admin@insureflow.test` |
| Corretor | `corretor@insureflow.test` |
| Assistente | `assistente@insureflow.test` |

A senha é o valor de `SEED_DEFAULT_PASSWORD` no `.env`. Os seeders se recusam a
rodar com `APP_ENV=production`.

## Modelo de dados

```
clients ──1:N──> policies <──N:1── insurers
                   │  ^  ^
                   │  │  └──N:1── insurance_products
                   │  └──N:1── users (broker_id)
                   ├──1:N──> coverages
                   ├──1:N──> installments
                   └──1:N──> claims ──1:N──> claim_status_histories

activity_logs ──polimórfica──> qualquer entidade auditável
```

Invariantes garantidas pelo próprio PostgreSQL:

- valores monetários em `decimal(12,2)` e CHECKs contra valores negativos;
- vigência válida (`ends_on > starts_on`) e sinistro comunicado após a ocorrência;
- `commission_amount` é coluna gerada a partir de `premium × commission_rate`;
- parcela paga se e somente se houver data de pagamento; "atrasado" não é gravado,
  é derivado (pendente com vencimento passado);
- sinistro aprovado exige valor aprovado, negado não pode tê-lo;
- CPF/CNPJ coerente com o tipo de pessoa e únicos entre registros não excluídos;
- `claim_status_histories` e `activity_logs` são somente inserção (trigger).

## Arquitetura do backend

```
Requisição -> Form Request (autoriza + valida) -> Controller (fino)
          -> Service (regras de negócio, quando existem) -> Model/Eloquent
          -> API Resource (formato JSON)
```

| Camada | Onde | Responsabilidade |
|---|---|---|
| Form Requests | `app/Http/Requests` | Validação, normalização (CPF/CNPJ, telefones e CEP só com dígitos) e autorização de escrita |
| Policies | `app/Policies` | Quem pode fazer o quê, por perfil |
| Services | `app/Services` | `PolicyService` (ciclo de vida da apólice), `ClaimService` (abertura e fluxo de sinistros), `DashboardService` (indicadores) |
| Enums | `app/Enums` | Status, transições permitidas e rótulos em português |
| Resources | `app/Http/Resources` | Formato das respostas; enums como `{value, label}`, dinheiro como string decimal |
| Auditoria | `app/Models/Concerns/HasActivityLog.php` | Grava `activity_logs` automaticamente pelos eventos dos models |

CRUDs sem regra própria (clientes, seguradoras, modalidades, usuários) não têm
Service: o controller usa o model diretamente.

## Autenticação

Sanctum em modo SPA: sessão em cookie **HttpOnly** + proteção **CSRF**. Nenhum
token é exposto ao JavaScript.

1. `GET /sanctum/csrf-cookie` — o servidor devolve o cookie `XSRF-TOKEN`.
2. `POST /api/login` com `{email, password, remember?}` enviando o header
   `X-XSRF-TOKEN` (o Axios faz isso automaticamente com `withXSRFToken: true`).
3. As demais requisições enviam o cookie de sessão e o header `X-XSRF-TOKEN`.
4. `POST /api/logout` invalida a sessão.

- Credenciais inválidas e e-mail inexistente recebem a mesma mensagem (422).
- Usuários inativos ou excluídos não autenticam; um usuário desativado com sessão
  aberta é desconectado na requisição seguinte (403).
- O login é limitado a 5 tentativas por minuto por e-mail + IP (429).
- `GET /api/user` retorna o usuário autenticado e o mapa `permissions`, calculado
  pelas Policies, para a SPA decidir o que exibir.

## Perfis e permissões

| Ação | Admin | Corretor | Assistente |
|---|:-:|:-:|:-:|
| Consultar todos os módulos e o dashboard | ✓ | ✓ | ✓ |
| Criar/editar clientes | ✓ | ✓ | ✓ |
| Excluir clientes | ✓ | ✓ | — |
| Seguradoras e modalidades (criar, editar, excluir) | ✓ | — | — |
| Apólices e coberturas (criar, editar, mudar status) | ✓ | ✓ | — |
| Excluir apólices (somente cotações) | ✓ | — | — |
| Registrar pagamento de parcelas | ✓ | ✓ | ✓ |
| Sinistros (abrir, editar, mudar status) | ✓ | ✓ | ✓ |
| Excluir sinistros | ✓ | — | — |
| Usuários e auditoria de usuários | ✓ | — | — |

## Regras de negócio

**Apólices**
- Nascem como cotação, pendente ou ativa; os demais status só por transição
  (`PolicyStatus::allowedTransitions()`): cotação → pendente → ativa ⇄ suspensa;
  ativa/suspensa → expirada; qualquer status não final → cancelada.
- Ativa exige vigência não encerrada; expirada exige vigência encerrada.
- Parcelas são geradas ao sair de cotação: prêmio dividido em centavos, diferença
  de arredondamento na 1ª parcela, vencimentos mensais a partir do início da vigência.
- Alterar prêmio, quantidade de parcelas ou início da vigência regenera as parcelas
  se nenhuma foi paga; caso contrário a alteração é recusada.
- Cancelar a apólice cancela as parcelas pendentes (as pagas são mantidas).
- Canceladas e expiradas só aceitam alteração de observações; só cotações podem ser excluídas.
- A comissão é calculada pelo banco (`commission_amount`, coluna gerada).

**Parcelas** — "atrasada" é derivada (pendente com vencimento passado), nunca
gravada. Apenas parcelas pendentes podem ser pagas, com data de pagamento até hoje.

**Sinistros**
- Apólice ativa, ou expirada se a ocorrência foi durante a vigência; a ocorrência
  deve estar dentro da vigência e não pode ser futura.
- Protocolo sequencial por ano (`SIN-2026-000042`), gerado sob advisory lock.
- Transições por `ClaimStatus::allowedTransitions()`; cada mudança grava
  `claim_status_histories`. Aprovar exige `approved_amount`; negar o remove.
- Sinistros encerrados não podem ser editados.

**Exclusões** — clientes, seguradoras e modalidades com apólices em andamento
não podem ser excluídos (apenas inativados). Todas as exclusões são lógicas.

**Auditoria** — criação, alteração, exclusão, restauração e mudança de status das
entidades de negócio são gravadas em `activity_logs` com o usuário autenticado
(ou nulo quando a origem é o sistema). Apenas campos alterados são guardados;
senhas e tokens nunca.

**Expiração automática** — `php artisan policies:expire` roda diariamente às
00:05 (container `scheduler`), expira apólices ativas/suspensas com vigência
encerrada e é idempotente.

## API

Todas as rotas abaixo exigem autenticação, exceto `/api/health` e `/api/login`.

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/health` | Verificação da aplicação e do banco |
| POST | `/api/login` · `/api/logout` | Autenticação |
| GET | `/api/user` | Usuário autenticado + permissões |
| GET, POST | `/api/clients` | Listar / criar clientes |
| GET, PUT, DELETE | `/api/clients/{id}` | Detalhe (com apólices) / editar / excluir |
| GET, POST | `/api/insurers` | Listar / criar seguradoras |
| GET, PUT, DELETE | `/api/insurers/{id}` | Detalhe / editar / excluir |
| GET, POST | `/api/insurance-products` | Listar / criar modalidades |
| GET, PUT, DELETE | `/api/insurance-products/{id}` | Detalhe / editar / excluir |
| GET, POST | `/api/policies` | Listar / criar apólices (com coberturas opcionais) |
| GET, PATCH, DELETE | `/api/policies/{id}` | Detalhe completo / editar / excluir cotação |
| PATCH | `/api/policies/{id}/status` | Transição de status `{status}` |
| GET, POST | `/api/policies/{id}/coverages` | Coberturas da apólice |
| GET, PUT, DELETE | `/api/coverages/{id}` | Detalhe / editar / excluir cobertura |
| GET | `/api/installments` | Parcelas (financeiro) |
| GET | `/api/installments/{id}` | Detalhe da parcela |
| POST | `/api/installments/{id}/payment` | Registrar pagamento `{paid_on}` |
| GET, POST | `/api/claims` | Listar / abrir sinistros |
| GET, PATCH, DELETE | `/api/claims/{id}` | Detalhe (com histórico) / editar / excluir |
| PATCH | `/api/claims/{id}/status` | Transição `{status, approved_amount?, notes?}` |
| GET, POST | `/api/users` | Usuários (somente admin) |
| GET, PUT, DELETE | `/api/users/{id}` | Detalhe / editar / excluir usuário |
| GET | `/api/activity-logs` | Auditoria (`subject_type`, `subject_id`, `user_id`, `action`) |
| GET | `/api/dashboard` | Indicadores (`expiring_within_days`, padrão 30) |
| GET | `/api/options` | Valores e rótulos dos enums e UFs |

**Listagens** são paginadas (`per_page`, até 100) e aceitam `sort=campo` ou
`sort=-campo` (apenas colunas permitidas). Principais filtros:

| Recurso | Filtros |
|---|---|
| clients | `search` (nome, e-mail, documento), `person_type`, `is_active`, `state`, `city` |
| insurers / insurance-products | `search`, `is_active` |
| policies | `search` (número, cliente), `status`, `client_id`, `insurer_id`, `insurance_product_id`, `broker_id`, `expiring_within_days` |
| installments | `status` (inclui `overdue`), `policy_id`, `client_id`, `due_from`, `due_until` |
| claims | `search` (protocolo, apólice, cliente), `status`, `open`, `policy_id`, `client_id`, `occurred_from`, `occurred_until` |
| users | `search`, `role`, `is_active` |

**Formato das respostas**

```jsonc
// sucesso: recurso único
{ "data": { "id": 1, "status": { "value": "active", "label": "Ativa" }, "premium": "1234.56" } }

// sucesso: listagem paginada
{ "data": [ ... ], "links": { ... }, "meta": { "current_page": 1, "total": 180, "per_page": 15 } }

// erro de validação ou de regra de negócio (422)
{ "message": "Os dados informados são inválidos.", "errors": { "document": ["O CPF informado é inválido."] } }

// demais erros (401, 403, 404, 405, 419, 429, 500)
{ "message": "Recurso não encontrado." }
```

Criação retorna **201**, exclusão **204**. Mensagens são em português e nunca
expõem classes, SQL ou stack traces (com `APP_DEBUG=false`).

## Comandos do dia a dia

```bash
docker compose up -d                           # sobe o ambiente
docker compose down                            # para o ambiente (mantém o banco)
docker compose down -v                         # para e APAGA o volume do banco

docker compose exec app php artisan migrate    # migrations
docker compose exec app php artisan test       # testes (banco insureflow_test)
docker compose exec app php artisan policies:expire   # expiração manual (normalmente via scheduler)
docker compose exec app php artisan route:list --path=api   # rotas da API
docker compose exec app composer require ...   # dependências PHP
docker compose exec node npm install ...       # dependências JS
docker compose exec node npm run build         # build de produção dos assets

docker compose logs -f app node                # logs
```

## Configuração

Todas as configurações ficam no `.env` (nunca versionado). Variáveis relevantes
para o ambiente Docker:

| Variável | Padrão | Uso |
|---|---|---|
| `APP_PORT` | `8000` | Porta HTTP da aplicação no host |
| `VITE_PORT` | `5173` | Porta do Vite no host |
| `FORWARD_DB_PORT` | `5433` | Porta do PostgreSQL no host (para clientes como DBeaver) |
| `DB_PASSWORD` | `change-me-local-only` | Senha do banco local; obrigatória |
| `HOST_UID` / `HOST_GID` | `1000` | Usuário dos containers PHP (relevante em hosts Linux) |
| `VITE_USE_POLLING` | `true` | Detecção de alterações em bind mounts do Windows/macOS |
| `SEED_DEFAULT_PASSWORD` | `password` | Senha dos usuários de demonstração (somente desenvolvimento) |

Se alterar `APP_PORT`, ajuste também `APP_URL` e `SANCTUM_STATEFUL_DOMAINS`.

## Testes

Os testes rodam no PostgreSQL (banco `insureflow_test`, criado automaticamente na
primeira inicialização do volume), para validar constraints e tipos reais do banco.

```bash
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=PolicyApiTest   # um arquivo específico
```

| Pasta | Cobertura |
|---|---|
| `tests/Unit` | Enums (transições), cálculo de parcelas, CPF/CNPJ |
| `tests/Feature/Auth` | Login, logout, conta inativa, rate limit, permissões por perfil |
| `tests/Feature/Api` | Endpoints de cada módulo: CRUD, filtros, validação, regras de negócio, autorização, formato de erros, dashboard |
| `tests/Feature/Database` | Constraints do PostgreSQL, relacionamentos, auditoria, seeders, fuso horário |
| `tests/Feature/Console` | Expiração automática de apólices e agendamento |

## Estrutura do projeto

```
├── app/
│   ├── Console/Commands/     # policies:expire
│   ├── Enums/                # status, perfis, formas de pagamento
│   ├── Exceptions/           # formato padronizado de erros da API
│   ├── Http/                 # Controllers/Api, Requests, Resources, Middleware
│   ├── Models/               # Eloquent + Concerns (auditoria, append-only)
│   ├── Policies/             # autorização por perfil
│   ├── Rules/                # CPF e CNPJ
│   ├── Services/             # regras de negócio (apólices, sinistros, dashboard)
│   └── Support/              # InstallmentSchedule, UFs
├── lang/pt_BR/               # mensagens de validação e da API
├── docker/
│   ├── nginx/default.conf
│   ├── php/Dockerfile        # PHP-FPM 8.4 + extensões + Composer
│   └── postgres/             # script de criação do banco de testes
├── resources/
│   ├── css/app.css           # Tailwind + tokens de cor da marca
│   ├── js/                   # SPA Vue
│   │   ├── components/  composables/  layouts/  pages/
│   │   ├── router/  services/  stores/  utils/
│   └── views/app.blade.php   # shell HTML da SPA
├── routes/{api,web}.php
├── tests/
└── compose.yaml
```

## Notas para Windows

- O projeto funciona a partir de qualquer drive (ex.: `E:\`). Se a performance do
  bind mount incomodar, clone o repositório dentro do sistema de arquivos do WSL2.
- Os finais de linha são normalizados para LF pelo `.gitattributes`; scripts shell
  com CRLF quebram dentro dos containers Linux.
- Se alguma porta estiver ocupada, altere `APP_PORT`, `VITE_PORT` ou
  `FORWARD_DB_PORT` no `.env`.
