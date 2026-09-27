# Desenvolvimento

Tudo roda em containers: não é preciso PHP, Composer, Node ou PostgreSQL no host.
Comandos PHP rodam no serviço `app`; comandos JavaScript, no serviço `node`.

## Ambiente

| Serviço | Função | Porta no host |
|---|---|---|
| `web` | nginx, ponto de entrada HTTP | `8000` |
| `app` | PHP-FPM com o Laravel | — |
| `scheduler` | `php artisan schedule:work` | — |
| `postgres` | PostgreSQL (`insureflow` e `insureflow_test`) | `5433` |
| `node` | Vite (HMR), build e testes do frontend | `5173` |

```bash
docker compose up -d            # sobe o ambiente
docker compose ps               # estado dos serviços
docker compose down             # para (mantém os volumes)
docker compose down -v          # para e APAGA banco e node_modules
docker compose up -d --build    # após mudar algo em docker/
```

## Comandos do dia a dia

```bash
# Banco
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed     # recria com dados de demonstração

# Testes e qualidade
docker compose exec app php artisan test                      # backend
docker compose exec app php artisan test --filter=PolicyApiTest
docker compose exec app vendor/bin/pint                       # formata o PHP
docker compose exec app vendor/bin/pint --test                # só verifica
docker compose exec node npm test                             # frontend
docker compose exec node npm run test:watch                   # frontend em modo watch
docker compose exec node npm run build                        # build de produção

# Inspeção
docker compose exec app php artisan route:list --path=api
docker compose exec app php artisan schedule:list
docker compose exec app php artisan policies:expire           # roda a expiração agora
docker compose exec app php artisan tinker

# Dependências
docker compose exec app composer require <pacote>
docker compose exec node npm install <pacote>
```

## Configuração (`.env`)

| Variável | Padrão | Uso |
|---|---|---|
| `APP_PORT` | `8000` | Porta HTTP no host |
| `VITE_PORT` | `5173` | Porta do Vite no host |
| `FORWARD_DB_PORT` | `5433` | PostgreSQL no host (DBeaver, psql) |
| `DB_PASSWORD` | `change-me-local-only` | Senha do banco local |
| `HOST_UID` / `HOST_GID` | `1000` | Usuário dos containers PHP (relevante em Linux) |
| `VITE_USE_POLLING` | `true` | Detecção de alterações em bind mounts do Windows/macOS |
| `SEED_DEFAULT_PASSWORD` | `password` | Senha das contas de demonstração |

Ao mudar `APP_PORT`, ajuste também `APP_URL` e `SANCTUM_STATEFUL_DOMAINS`: o
Sanctum só aceita a sessão da SPA vinda de um domínio listado ali.

Para acessar o banco de fora do Docker: host `localhost`, porta `5433`, banco
`insureflow`, usuário `insureflow` e a senha do `.env`.

## Frontend

- Código em `resources/js` (organização em [architecture.md](architecture.md#frontend)).
- O Vite roda dentro do container `node` com HMR. A página é servida pelo nginx em
  `:8000`, e os assets vêm do Vite em `:5173`, que é indicado ao Laravel pelo
  arquivo `public/hot`.
- Toda chamada à API passa por `resources/js/api`. Para um endpoint novo, adicione a
  função ali, e não chame o Axios no componente.
- Rótulos de enums vêm de `/api/options` (`useOptionsStore`), e permissões de
  `/api/user` (`useAuthStore().can(...)`). Não duplique esses valores no Vue.
- Testes em `resources/js/tests` (Vitest + @vue/test-utils em jsdom);
  `tests/support.js` monta páginas com Pinia, router e usuário autenticado.

## Backend

- Uma regra de negócio nova vai para o Service do módulo (ou para um predicado do
  model, se o frontend também precisar dela), e não para o controller.
- Validação em Form Requests; autorização em Policies (quem pode) e Services
  (quando pode).
- `Model::shouldBeStrict()` está ativo: um lazy loading em lista (N+1) vira erro no
  desenvolvimento. Use `with()` nas consultas das listagens. `QueryBudgetTest`
  detecta regressões.
- Os testes usam o banco `insureflow_test`, com `RefreshDatabase`.

## Depuração

```bash
docker compose logs -f app node web          # logs dos containers
docker compose exec app php artisan pail     # acompanha o log do Laravel em tempo real
```

- O log da aplicação fica em `storage/logs/laravel.log`.
- Com `APP_DEBUG=true` (padrão local), erros 500 da API trazem mensagem e trace.
- `DB::enableQueryLog()` no `tinker` mostra as queries de uma operação.

## Solução de problemas

| Sintoma | Causa provável | Solução |
|---|---|---|
| Página em branco e erros de assets em `:5173` | `public/hot` sobrou de um Vite que não está rodando | `docker compose up -d node` ou apague `public/hot` |
| Login responde 419 | `APP_URL`/`SANCTUM_STATEFUL_DOMAINS` não batem com o endereço acessado | Acesse `http://localhost:8000` ou ajuste as duas variáveis |
| Porta ocupada ao subir | Outro serviço usando 8000/5173/5433 | Mude `APP_PORT`, `VITE_PORT` ou `FORWARD_DB_PORT` |
| `scheduler` reiniciando | `vendor/` ainda não instalado | Rode `docker compose exec app composer install` |
| Arquivos criados como root (Linux) | UID do container diferente do seu | Ajuste `HOST_UID`/`HOST_GID` e `docker compose up -d --build` |
| Respostas lentas no Windows | Bind mount do Docker Desktop | Clone o projeto dentro do WSL2 |
| `node_modules` não aparece na pasta | Ele fica em um volume do Docker, de propósito | Use `docker compose exec node npm ...` |
| Timestamps com 3 h de diferença | Fuso da sessão do PostgreSQL diferente da aplicação | Mantenha `APP_TIMEZONE` no `.env` (a conexão usa esse fuso) |
