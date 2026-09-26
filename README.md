# InsureFlow

Sistema web de gestão para corretoras de seguros: clientes, seguradoras, modalidades,
apólices, coberturas, parcelas, sinistros e dashboard de indicadores.

> **Status:** Fases 1 (fundação) e 2 (banco de dados) concluídas. Os módulos de negócio serão adicionados
> incrementalmente. A documentação completa (arquitetura, endpoints, screenshots)
> será expandida ao longo do desenvolvimento.

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

## Comandos do dia a dia

```bash
docker compose up -d                           # sobe o ambiente
docker compose down                            # para o ambiente (mantém o banco)
docker compose down -v                         # para e APAGA o volume do banco

docker compose exec app php artisan migrate    # migrations
docker compose exec app php artisan test       # testes (banco insureflow_test)
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
```

## Estrutura do projeto

```
├── app/                      # Backend Laravel (Controllers/Api, Models, Services…)
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
