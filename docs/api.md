# API

API REST em `/api/*`, consumida pela SPA. Todas as rotas exigem sessão autenticada,
exceto `GET /api/health` e `POST /api/login`. `php artisan route:list --path=api`
lista as 47 rotas.

## Autenticação (Sanctum SPA)

Sessão em cookie **HttpOnly** com proteção **CSRF**. Nenhum token é exposto ao
JavaScript.

1. `GET /sanctum/csrf-cookie`: o servidor devolve o cookie `XSRF-TOKEN`.
2. `POST /api/login` com `{email, password, remember?}` enviando o header
   `X-XSRF-TOKEN` (o Axios faz isso com `withXSRFToken: true`).
3. As demais requisições enviam o cookie de sessão e o header `X-XSRF-TOKEN`.
4. `POST /api/logout` invalida a sessão e regenera o token CSRF.

| Situação | Resposta |
|---|---|
| E-mail inexistente ou senha errada | 422, mesma mensagem nos dois casos |
| Conta inativa (senha correta) | 422, "conta inativa" |
| Conta desativada durante a sessão | 403 na requisição seguinte e sessão encerrada |
| Sem sessão | 401 |
| Token CSRF ausente/expirado | 419 |
| Mais de 5 tentativas de login por minuto (e-mail + IP) | 429 com `Retry-After` |
| Mais de 180 requisições autenticadas por minuto (por usuário) | 429 com `Retry-After` |

O e-mail é comparado sem diferenciar maiúsculas de minúsculas.

`GET /api/user` devolve o usuário e o mapa `permissions`, calculado pelas Policies.
O frontend o usa só para decidir o que exibir; a autorização real acontece em cada
requisição.

## Perfis

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

Todos os perfis consultam todos os registros: não há carteira por corretor (veja
"Limitações conhecidas" em [architecture.md](architecture.md)).
`tests/Feature/Security/AuthorizationMatrixTest.php` exercita toda operação de
escrita com cada perfil.

## Rotas

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/health` | Aplicação e conexão com o banco |
| POST | `/api/login` · `/api/logout` | Autenticação |
| GET | `/api/user` | Usuário autenticado + permissões |
| GET, POST | `/api/clients` | Listar / criar clientes |
| GET, PUT, DELETE | `/api/clients/{id}` | Detalhe (com apólices) / substituir / excluir |
| GET, POST | `/api/insurers` | Listar / criar seguradoras |
| GET, PUT, DELETE | `/api/insurers/{id}` | Detalhe / substituir / excluir |
| GET, POST | `/api/insurance-products` | Listar / criar modalidades |
| GET, PUT, DELETE | `/api/insurance-products/{id}` | Detalhe / substituir / excluir |
| GET, POST | `/api/policies` | Listar / criar apólices (coberturas opcionais no cadastro) |
| GET, PATCH, DELETE | `/api/policies/{id}` | Detalhe completo / edição parcial / excluir cotação |
| PATCH | `/api/policies/{id}/status` | Transição de status `{status}` |
| GET, POST | `/api/policies/{id}/coverages` | Coberturas da apólice |
| GET, PUT, DELETE | `/api/coverages/{id}` | Detalhe / substituir / excluir cobertura |
| GET | `/api/installments` | Parcelas de todas as apólices |
| GET | `/api/installments/{id}` | Detalhe da parcela |
| POST | `/api/installments/{id}/payment` | Registrar pagamento `{paid_on}` |
| GET, POST | `/api/claims` | Listar / abrir sinistros |
| GET, PATCH, DELETE | `/api/claims/{id}` | Detalhe (com histórico) / editar dados descritivos / excluir |
| PATCH | `/api/claims/{id}/status` | Transição `{status, approved_amount?, notes?}` |
| GET, POST | `/api/users` | Usuários (somente admin) |
| GET, PUT, DELETE | `/api/users/{id}` | Detalhe / substituir / excluir |
| GET | `/api/activity-logs` | Auditoria |
| GET | `/api/dashboard` | Indicadores (`expiring_within_days`, 1–90, padrão 30) |
| GET | `/api/options` | Valores e rótulos de enums, UFs, entidades e campos |

As rotas `apiResource` também aceitam `PATCH` onde a tabela indica `PUT`, e vice-versa.
Clientes, seguradoras, modalidades, coberturas e usuários validam o registro completo;
apólices e sinistros aceitam edição parcial. Status nunca mudam por essas rotas,
apenas pelas rotas `/status`, que aplicam as transições e gravam o histórico.

Identificadores de rota são numéricos (até 18 dígitos). Qualquer outro valor
(`/api/clients/abc`) resulta em 404.

## Listagens

Paginadas (`page`, `per_page` de 1 a 100, padrão 15) e ordenáveis por `sort=campo`
ou `sort=-campo` (decrescente), apenas nas colunas permitidas. Filtros e parâmetros
inválidos resultam em 422; parâmetros desconhecidos são ignorados.

| Recurso | Filtros | Ordenação |
|---|---|---|
| clients | `search` (nome, e-mail, CPF/CNPJ), `person_type`, `is_active`, `state`, `city` | `name`, `created_at` |
| insurers | `search` (nomes, CNPJ), `is_active` | `trade_name`, `created_at` |
| insurance-products | `search`, `is_active` | `name`, `created_at` |
| policies | `search` (número, cliente), `status`, `client_id`, `insurer_id`, `insurance_product_id`, `broker_id`, `expiring_within_days` | `number`, `starts_on`, `ends_on`, `premium`, `created_at` |
| installments | `status` (efetivo, inclui `overdue`), `policy_id`, `client_id`, `due_from`, `due_until` | `due_date`, `amount`, `paid_on` |
| claims | `search` (protocolo, apólice, cliente), `status`, `open`, `policy_id`, `client_id`, `occurred_from`, `occurred_until` | `protocol`, `occurred_on`, `reported_on`, `estimated_amount` |
| users | `search`, `role`, `is_active` | `name`, `created_at` |
| activity-logs | `subject_type` (alias: `client`, `policy`...), `subject_id`, `user_id`, `action` | `created_at` |

## Formato das respostas

```jsonc
// recurso único (201 na criação)
{ "data": { "id": 1, "status": { "value": "active", "label": "Ativa" }, "premium": "1234.56" } }

// listagem paginada
{ "data": [ ... ], "links": { ... }, "meta": { "current_page": 1, "last_page": 12, "total": 180, "per_page": 15 } }

// validação ou regra de negócio (422)
{ "message": "Os dados informados são inválidos.", "errors": { "document": ["O CPF informado é inválido."] } }

// demais erros (401, 403, 404, 405, 419, 429, 500)
{ "message": "Recurso não encontrado." }
```

- Exclusão retorna **204** sem corpo.
- Enums são `{value, label}`: `value` para enviar à API, `label` para exibir.
- Dinheiro é string decimal (`"1234.56"`), nunca número de ponto flutuante.
- Datas de negócio são `YYYY-MM-DD`; timestamps são ISO 8601 com offset.
- Mensagens estão em português e nunca expõem classes, SQL ou stack trace
  (com `APP_DEBUG=false`).

## O que pode ser feito com cada registro

A API informa quais ações são possíveis, calculadas pelas mesmas regras que o
backend aplica, para que o frontend não precise replicá-las:

| Campo | Onde | Significado |
|---|---|---|
| `allowed_transitions` | apólices, sinistros | Status para os quais o registro pode ir agora |
| `is_editable` | apólices, sinistros | Se ainda aceita edição (não está em estado final) |
| `is_deletable` | apólices | Se pode ser excluída (somente cotações) |
| `is_payable` | parcelas | Se o pagamento pode ser registrado |
| `permissions` | `/api/user` | O que o perfil do usuário permite |
