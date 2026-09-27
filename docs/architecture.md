# Arquitetura

## Visão geral

```
Navegador ──HTTP──> nginx (web) ──FastCGI──> PHP-FPM / Laravel (app) ──> PostgreSQL
    │                  │
    │                  └─ arquivos estáticos (public/, build do Vite)
    └─ em desenvolvimento, assets e HMR vêm do Vite (node :5173)

scheduler (mesma imagem do app) ──> php artisan schedule:work ──> policies:expire
```

Uma única aplicação Laravel expõe a API em `/api/*` e entrega uma view que carrega
a SPA Vue. Qualquer outra URL é resolvida pelo Vue Router no navegador. Frontend e
backend ficam no mesmo repositório e no mesmo domínio, mas se comunicam apenas pela
API.

**Por que não dois projetos separados?** No mesmo domínio, o Sanctum autentica a SPA
por sessão (cookie HttpOnly + CSRF), sem tokens acessíveis ao JavaScript e sem CORS.
Para o tamanho do projeto, um único repositório e um único deploy são mais simples
de manter.

## Backend

```
Requisição → middleware (auth:sanctum, active, throttle)
           → Form Request (autoriza + normaliza + valida)
           → Controller (fino)
           → Service (quando há regra de negócio) → Model/Eloquent → PostgreSQL
           → API Resource (formato JSON)
```

| Camada | Onde | Decisão |
|---|---|---|
| Form Requests | `app/Http/Requests` | Validam e normalizam a entrada (documentos, telefones e CEP só com dígitos; e-mail em minúsculas). Requisições de escrita autorizam antes de validar: sem permissão, a resposta é 403 e não 422. |
| Policies | `app/Policies` | Uma matriz de perfis (quem pode). Regras que dependem do estado do registro (quando pode) ficam nos Services. |
| Services | `app/Services` | Só onde há regra: `PolicyService` (ciclo de vida, parcelas, expiração), `ClaimService` (elegibilidade, protocolo, histórico), `DashboardService` (agregações). CRUDs simples usam o model direto. |
| Enums | `app/Enums` | Status, transições permitidas e rótulos em português. São a fonte da máquina de estados. |
| Models | `app/Models` | Relacionamentos, scopes de filtro e predicados (`isEditable`, `isPayable`, `termAllows`, `availableTransitions`...) usados tanto pelos Services quanto pelos Resources. |
| Resources | `app/Http/Resources` | Formato das respostas; expõem o que pode ser feito com cada registro. |
| Exceções | `app/Exceptions` | `BusinessRuleException` vira 422 no formato de validação; `ApiExceptionRenderer` padroniza todos os erros em português, sem detalhes internos. |
| Auditoria | `app/Models/Concerns/HasActivityLog.php` | Grava `activity_logs` pelos eventos dos models: ninguém precisa lembrar de chamar. |

`Model::shouldBeStrict()` fica ativo fora de produção: lazy loading em coleções
(N+1), atributos não carregados e mass assignment silenciosamente descartado viram
erros durante o desenvolvimento e os testes.

## Banco de dados

O PostgreSQL garante as invariantes, e não só a aplicação: CHECK constraints,
coluna gerada para a comissão, índices únicos parciais que ignoram registros
excluídos e trigger para históricos imutáveis. Assim, dados inválidos são
recusados mesmo vindos de seeders, scripts ou SQL manual. Veja a tabela
"Onde cada regra é garantida" em [domain-rules.md](domain-rules.md).

- Dinheiro em `decimal(12,2)`; datas de negócio em `date`; timestamps em `timestamptz`.
- O fuso da sessão do PostgreSQL é o da aplicação (`APP_TIMEZONE`,
  America/Sao_Paulo). Sem isso, os timestamps seriam gravados 3 horas deslocados.
- Chaves estrangeiras das apólices usam RESTRICT; filhos da apólice (coberturas,
  parcelas) usam CASCADE; referências a usuários em históricos usam SET NULL.
- Os testes rodam em um banco PostgreSQL separado (`insureflow_test`), para
  exercitar constraints e tipos reais.

## Frontend

SPA em Vue 3 com JavaScript (Composition API), Vue Router, Pinia, Axios e
Tailwind CSS 4.

| Pasta (`resources/js`) | Responsabilidade |
|---|---|
| `api/` | Único ponto de acesso HTTP: Axios configurado para Sanctum, conversão de erros em `ApiError`, funções por recurso |
| `stores/` | Pinia: sessão (`/api/user`), opções (`/api/options`), notificações, diálogo de confirmação |
| `composables/` | `useForm`, `usePaginatedList`, `useAsyncData`, listas para selects |
| `router/` | Rotas por módulo e guards (login, `meta.permission` com as chaves do backend) |
| `layouts/` | Layout autenticado: sidebar (gaveta no mobile), header, logout |
| `components/` | `ui/` (botões, modal, estados), `form/` (campos acessíveis, dinheiro, máscaras, busca remota), `data/` (tabela, paginação, busca) e componentes por módulo |
| `pages/` | Uma página por tela, organizadas por módulo |
| `utils/` | Formatação pt-BR (datas sem deslocamento de fuso, moeda, documentos) e máscaras |
| `tests/` | Vitest + @vue/test-utils |

| Rota | Tela | Permissão exigida |
|---|---|---|
| `/login` | Login | — |
| `/` | Dashboard | — |
| `/clients`, `/clients/:id` | Clientes e detalhe (apólices, histórico) | — |
| `/clients/new`, `/clients/:id/edit` | Cadastro e edição de cliente | `clients.create` / `clients.update` |
| `/insurers`, `/insurers/:id` | Seguradoras | — |
| `/insurers/new`, `/insurers/:id/edit` | Cadastro e edição de seguradora | `insurers.manage` |
| `/insurance-products` | Modalidades (cadastro em modal) | edição: `insurance_products.manage` |
| `/policies`, `/policies/:id` | Apólices e visão central da apólice | — |
| `/policies/new`, `/policies/:id/edit` | Cadastro e edição de apólice | `policies.manage` |
| `/installments` | Consulta financeira de parcelas | pagamento: `installments.pay` |
| `/claims`, `/claims/:id` | Sinistros e detalhe com histórico de status | — |
| `/claims/new` | Abertura de sinistro | `claims.manage` |
| `/users` | Usuários | `users.manage` |
| `/activity-logs` | Auditoria | `activity_logs.view` |

- **Uma camada de API** (`resources/js/api`): nenhum componente usa o Axios
  diretamente. O cliente trata o CSRF, repete a requisição após um 419, avisa a
  aplicação num 401 (sessão expirada) e converte qualquer erro em `ApiError`
  no formato da API.
- **O backend é a fonte da verdade**: usuário e permissões vêm de `/api/user`;
  rótulos de enums, entidades e campos, de `/api/options`; as ações possíveis em
  cada registro, dos próprios resources. O frontend não conhece a máquina de
  estados nem a matriz de perfis.
- **Estado na URL**: filtros, ordenação e página das listagens ficam na query
  string, e a API faz toda a filtragem e ordenação.
- **Composables** concentram o comportamento repetido: `useForm` (erros 422 por
  campo, bloqueio de envio duplo), `usePaginatedList`, `useAsyncData`.
- **Acessibilidade**: campos com rótulo e erro associados (`aria-describedby`,
  `aria-invalid`), modais com foco preso e Esc, foco movido ao título a cada troca de
  página, gaveta mobile com gestão de foco e conteúdo inerte, tabelas com `aria-sort`,
  gráfico com tabela alternativa e contraste AA.

## Docker

| Serviço | Imagem | Observação |
|---|---|---|
| `web` | nginx 1.27 | Resolve o `app` pelo DNS do Docker a cada requisição (recriar o container não derruba o nginx). Não expõe versão; envia headers de segurança. |
| `app` | `docker/php` (PHP 8.4-FPM) | Extensões mínimas + Composer. Usuário com UID/GID do host (relevante em Linux). |
| `scheduler` | mesma do `app` | `php artisan schedule:work`. |
| `postgres` | PostgreSQL 17 | Volume nomeado; cria o banco de testes na primeira inicialização. |
| `node` | `docker/node` (Node 22) | Vite, build e testes. `node_modules` fica em volume nomeado. |

**Desempenho no Docker Desktop (Windows/macOS).** Ler muitos arquivos por bind mount
é lento. Por isso:
- o `node_modules` fica em volume próprio (o Vitest caiu de 167 s para ~5 s);
- o Vite vigia apenas `resources/` e `routes/`, com polling a cada 1 s;
- o opcache revalida os arquivos PHP a cada 2 s, e não a cada requisição.

Para o melhor desempenho no Windows, clone o projeto dentro do sistema de arquivos
do WSL2.

## Segurança

- Sessão em cookie HttpOnly, SameSite=Lax, com CSRF; sessão regenerada no login e
  invalidada no logout. Nenhum token no `localStorage`.
- Rate limit: 5 tentativas de login por minuto (e-mail + IP) e 180 requisições
  autenticadas por minuto por usuário.
- Toda rota da API, exceto health e login, exige autenticação: um teste enumera as
  rotas e falha se alguma nova esquecer o middleware.
- Autorização sempre no backend (Policies + Form Requests), coberta por um teste que
  exercita toda operação de escrita com cada perfil.
- Campos controlados pelo sistema (status, protocolo, comissão, vínculos, perfis)
  não são aceitos da requisição: ou são proibidos na validação (422) ou ignorados.
- IDs de rota restritos a números, e filtros, ordenação e paginação validados por
  lista permitida.
- Erros em português, sem classes, SQL ou stack trace quando `APP_DEBUG=false`.
- Auditoria imutável: trigger no banco + ausência de endpoints de escrita.

## Produção

O ambiente Docker deste repositório é de **desenvolvimento**. Para publicar:

- `APP_ENV=production`, `APP_DEBUG=false`, um `APP_KEY` próprio e `APP_URL` com HTTPS;
- `SESSION_SECURE_COOKIE=true` e, preferencialmente, `SESSION_ENCRYPT=true`;
- `SANCTUM_STATEFUL_DOMAINS` com o domínio real;
- credenciais do banco próprias, fora do repositório;
- assets gerados com `npm run build`, sem o servidor Vite (e sem o arquivo `public/hot`);
- opcache sem revalidação (`opcache.validate_timestamps=0`) e
  `php artisan config:cache route:cache view:cache`;
- não rodar os seeders: eles criam contas de demonstração e se recusam a rodar com
  `APP_ENV=production`;
- `LOG_LEVEL` adequado (por exemplo, `warning`).

## Limitações conhecidas

- **Sem carteira por corretor**: todos os perfis consultam todos os registros. É uma
  decisão de escopo (corretora pequena, equipe única); restringir exigiria filtrar as
  consultas pelo `broker_id` do usuário.
- **Busca por texto** usa `ILIKE '%termo%'`, que não aproveita índices. Suficiente
  para milhares de registros; em volumes maiores, o caminho seria `pg_trgm`.
- **Sem recuperação de senha por e-mail**: senhas são definidas pelo administrador.
- **Sem testes end-to-end automatizados** no navegador: os fluxos foram validados
  manualmente, e a integração é coberta por testes de API (PHPUnit) e de componentes
  com a API simulada (Vitest).
- **Acessibilidade e responsividade verificadas manualmente**: não há ferramenta
  automatizada (como axe) no projeto, e o layout móvel foi validado com viewport de
  390 px no navegador, não em aparelhos reais.
- **Latência no Docker Desktop (Windows)**: respostas típicas de ~0,2 s, com picos
  de até ~1,7 s quando o opcache revalida arquivos no bind mount. Clonar o projeto
  dentro do WSL2 resolve.
- **Scheduler num clone novo**: até o `composer install` terminar, o container
  `scheduler` reinicia em loop (não há `vendor/`). É inofensivo, mas polui o log.
- **Auditoria dos dados de demonstração**: os seeders rodam sem eventos de model,
  então os registros de exemplo não têm histórico de auditoria. A trilha passa a
  existir a partir das operações feitas pela interface ou pela API.
