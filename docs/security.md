# Auditoria de segurança

Revisão feita em 2026-09-28 sobre a versão 1.0.0, cobrindo backend (Laravel 13 +
Sanctum), frontend (Vue 3) e o ambiente Docker. Para cada item: o que foi
encontrado e o que mudou.

| # | Item | Situação encontrada | Ação |
|---|------|---------------------|------|
| 1 | `.env` exposto | Fora do Git (`.gitignore`, nunca commitado); nginx nega arquivos ocultos. Porém PostgreSQL (com a senha de exemplo), Vite e nginx escutavam em `0.0.0.0`, acessíveis pela rede local. | Portas publicadas em `127.0.0.1` (`FORWARD_BIND_ADDRESS`). |
| 2 | Validação no frontend | Formulários com `novalidate`, dependendo só do 422 da API. | Regras em `utils/validation.js`, aplicadas por `useForm` antes do envio. |
| 3 | Validação no backend | FormRequest em todas as escritas, com enums, limites, CPF/CNPJ e `prohibited`. | Limites de tamanho acrescentados ao login. |
| 4 | SQL injection | Eloquent/Query Builder com parâmetros; `selectRaw` só com bindings; ordenação por lista de colunas permitidas. | `%` e `_` da busca escapados (`App\Support\Like`). |
| 5 | Autenticação fraca | Sessão Sanctum HttpOnly, regeneração no login, bcrypt 12. Senha mínima de 8 caracteres e "manter conectado" com a duração padrão (anos). | `Password::defaults()` (10-72, maiúsculas, minúsculas, números; vazadas em produção); lembrar-me por 30 dias. |
| 6 | IDOR | Toda rota passa por Policy; IDs validados como inteiros. A carteira é compartilhada entre os perfis por regra de negócio (corretora única); logs de usuários e gestão de usuários só para administrador. | Nenhuma alteração necessária. |
| 7 | Senhas no banco/Docker | Senhas com hash (`hashed` cast); Compose lê credenciais do `.env` e exige `DB_PASSWORD`. A senha de exemplo do banco é pública, então a exposição do item 1 era o risco real. | Resolvido pelo item 1; troque a senha local se a porta já esteve aberta. |
| 8 | Força bruta | 5 tentativas/min por e-mail + IP; um IP podia testar uma senha em vários e-mails e ataques distribuídos não tinham limite por conta. | Limites adicionais: 20/min por IP e 30/hora por e-mail. |
| 9 | Bloqueio durante envio | `useForm` e `AppButton` impedem envios duplicados; exclusões e transições desabilitam o botão. | Nenhuma alteração necessária. |
| 10 | CSRF | Sanctum stateful com `X-XSRF-TOKEN`, SameSite=Lax. | Nenhuma alteração necessária. |
| 11 | Upload sem validação | Não há upload, mas PHP aceitava arquivos de até 20 MB e nginx corpos de 25 MB. | `file_uploads = Off`; corpo limitado a 1 MB. |
| 12 | Revelação de informações | Erros padronizados sem detalhes internos com `APP_DEBUG=false`; `server_tokens off` e `expose_php = Off`. | Cabeçalhos `Permissions-Policy`, COOP e `X-Frame-Options: DENY`. |
| 13 | Dependências vulneráveis | Lockfiles nas versões mais recentes (Laravel 13.33, Sanctum 4.3.3, axios 1.20, Vite 8.3). `composer audit` e `npm audit` não puderam rodar (sem acesso à internet no momento da revisão). | Rodar `composer audit` e `npm audit` (ver abaixo). |
| 14 | Tokens | Não há tokens de API (sem `HasApiTokens`); só sessão. O cookie "lembrar-me" durava anos. | Lembrar-me limitado a 30 dias. |
| 15 | Rate limit | Login e rotas autenticadas limitados; `/api/health` público sem limite. | `/api/health` limitado a 60/min por IP. |
| 16 | Dados sensíveis | Senha e `remember_token` ocultos nas respostas e fora da auditoria; sessões gravadas sem criptografia. | `SESSION_ENCRYPT=true` por padrão. |
| 17 | SSRF | O backend não faz requisições HTTP de saída. | Não se aplica. |
| 18 | Cookies inseguros | HttpOnly e SameSite=Lax; `secure` dependia de lembrar `SESSION_SECURE_COOKIE` em produção. | `secure` automático quando `APP_URL` usa https. |
| 19 | CORS | Sem `config/cors.php`: o padrão do framework libera qualquer origem (sem credenciais). | CORS restrito a `APP_URL` (+ `CORS_ALLOWED_ORIGINS`). |

## Verificação de dependências

```bash
docker compose exec app composer audit
docker compose exec node npm audit
```

## Recomendações para produção

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` com https e HSTS no proxy.
- Content-Security-Policy no servidor que entrega o build (não aplicada aqui porque
  o servidor Vite de desenvolvimento roda em outra origem).
- Senhas próprias para o banco e para os usuários; os seeders de demonstração se
  recusam a rodar em produção.
