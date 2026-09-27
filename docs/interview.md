# Guia de estudo para entrevistas

Perguntas prováveis sobre o InsureFlow, com respostas baseadas no que foi realmente
implementado. Os arquivos citados servem para mostrar o código durante a conversa.

## Visão geral

### Em uma frase, o que é o InsureFlow?

Um sistema de gestão para corretoras de seguros: clientes, seguradoras, apólices com
coberturas e parcelas, sinistros e auditoria, com as regras do negócio garantidas no
backend e no banco. É um monólito Laravel com API REST, uma SPA Vue e PostgreSQL,
rodando inteiramente em Docker.

### Qual parte do projeto demonstra mais complexidade?

O ciclo de vida da apólice (`app/Services/PolicyService.php`), que reúne:
- as transições de status definidas no enum;
- as condições de vigência;
- a geração de parcelas em centavos, com o arredondamento na primeira;
- a regeneração das parcelas quando o prêmio muda antes de qualquer pagamento;
- o cancelamento das parcelas pendentes;
- a expiração automática.

Tudo isso é coberto por testes de API e de integridade.

## Arquitetura

### Por que Laravel + Vue no mesmo projeto?

Por estarem no mesmo domínio, a SPA pode usar o Sanctum em modo sessão, sem tokens no
navegador e sem CORS, e o projeto tem um único repositório e um único deploy. A
separação existe na comunicação: o Vue só fala com a API (`resources/js/api`), e o
Laravel só entrega a página inicial e a API. Separar os projetos exigiria configurar
CORS e os domínios do Sanctum, o que não se justificava neste porte.

### Como o backend é organizado?

Form Request (autoriza, normaliza e valida) → controller fino → Service (só quando há
regra de negócio) → model → API Resource. Existem três Services (`Policy`, `Claim`,
`Dashboard`); os CRUDs simples não têm um, para não criar camadas vazias.

### Por que PostgreSQL?

Porque o projeto usa recursos que tornam o banco a última linha de defesa:
- CHECK constraints (valores não negativos, vigência válida, formato de CPF/CNPJ, coerência de status);
- coluna gerada para a comissão;
- índices únicos parciais que ignoram registros excluídos;
- trigger que torna os históricos imutáveis;
- `jsonb` na auditoria;
- `COUNT/SUM ... FILTER` no dashboard;
- advisory lock no protocolo de sinistros.

Os testes rodam em PostgreSQL justamente para exercitar esses recursos.

### Por que Sanctum em modo SPA, e não tokens?

Um token em `localStorage` pode ser lido por qualquer script injetado (XSS). No modo SPA:
- a sessão fica num cookie HttpOnly, que o JavaScript não lê;
- o CSRF é protegido pelo cookie `XSRF-TOKEN`;
- a sessão é regenerada no login e invalidada no logout.

O Axios envia o header automaticamente. Em caso de 419 (token CSRF expirado), o
cliente renova o cookie e repete a requisição uma vez. Veja `resources/js/api/http.js`.

## Regras de negócio

### Por que a comissão é uma coluna gerada?

Porque é um valor derivado (`prêmio × taxa`). Na Fase 2, a primeira ideia era calcular
num Service, mas isso permitiria gravar um valor divergente por outro caminho (seeder,
tinker, SQL). Com `GENERATED ALWAYS AS ... STORED`:
- o banco garante a coerência;
- o valor continua armazenado, e o dashboard o soma com SQL;
- a API ignora a coluna, e o banco recusa escrita direta nela;
- o model a relê após salvar (`#[Refreshes]`).

### Como funcionam as transições de status?

- **Máquina de estados:** fica nos enums (`PolicyStatus::allowedTransitions()`,
  `ClaimStatus::allowedTransitions()`).
- **Condições de vigência:** ficam em `Policy::termAllows()`. Só fica ativa se a
  vigência não terminou; só expira depois que termina.
- **Efeitos colaterais:** ficam nos Services, como gerar parcelas ao emitir, cancelar
  as pendentes ao cancelar e gravar o histórico do sinistro.

O status nunca muda pelo formulário comum, apenas pelas rotas `PATCH .../status`.

### Como o sistema evita regras duplicadas no frontend?

A API informa o que é possível:
- `allowed_transitions`, `is_editable`, `is_deletable` e `is_payable` em cada registro;
- `permissions` no `/api/user`;
- rótulos de enums, entidades e campos no `/api/options`.

Esses valores são calculados pelos mesmos métodos que os Services usam para validar.
Um exemplo real: na Fase 6, a API oferecia "expirar" para uma apólice ainda vigente, e
a ação sempre falhava. A correção foi levar a condição de vigência para o model e
usá-la tanto na validação quanto na lista de transições, sem tocar no Vue.

### Como são tratadas as parcelas atrasadas?

"Atrasado" não é gravado. O banco guarda pendente, pago ou cancelado, e o atraso é o
status efetivo de uma parcela pendente com vencimento passado. Ele é calculado em
`Installment::effectiveStatus()`, no scope `overdue()` e no dashboard. Assim o dado
nunca fica desatualizado, como ficaria se dependesse de um job diário. A constraint
do banco inclusive recusa gravar `overdue`.

### Como as parcelas são geradas?

`App\Support\InstallmentSchedule` divide o prêmio em centavos inteiros e coloca a
diferença de arredondamento na primeira parcela: R$ 1.000,00 em 3x vira 333,34 + 333,33
+ 333,33, e a soma sempre fecha. Os vencimentos são mensais, sem "transbordar" o mês
(31/01 → 28/02). É uma classe pura, testada isoladamente.

### Como funcionam os sinistros?

- **Elegibilidade:** só podem ser abertos para apólice ativa, ou expirada quando a
  ocorrência foi durante a vigência. A data não pode ser futura.
- **Protocolo:** sequencial por ano (`SIN-2026-000042`), gerado sob
  `pg_advisory_xact_lock` para que duas aberturas simultâneas não repitam o número.
- **Histórico:** cada transição grava uma linha imutável em `claim_status_histories`.
- **Valor aprovado:** a aprovação exige o valor, e a negativa o remove, com constraints
  no banco garantindo as duas regras.

### Como funciona a auditoria?

O trait `HasActivityLog` escuta os eventos do Eloquent (criação, alteração, exclusão,
restauração) e grava em `activity_logs`:
- o usuário autenticado, ou nulo quando a origem é o sistema;
- o registro afetado, via relação polimórfica com aliases do morph map;
- apenas os campos alterados (`{old, new}`), nunca senhas nem tokens.

Uma alteração de `status` vira `status_changed`. Um trigger do PostgreSQL rejeita
`UPDATE`, e a API não tem rota para escrever ou apagar logs.

### Como funciona a expiração automática?

O comando `policies:expire` está agendado para 00:05 em `routes/console.php` e roda
no container `scheduler` (`schedule:work`). Ele seleciona só apólices ativas ou
suspensas com vigência encerrada e aplica a mesma transição da API, então é
**idempotente**: rodar duas vezes não altera nada na segunda. Cada expiração é
auditada com usuário nulo.

## Segurança e qualidade

### Como as permissões são controladas?

- **Backend:** Policies com uma matriz de três perfis. As Form Requests autorizam antes
  de validar, então quem não tem permissão recebe 403 e não 422.
- **Frontend:** recebe o mapa `permissions` só para decidir o que exibir.
- **Teste:** `AuthorizationMatrixTest` executa todas as 26 operações de escrita com cada
  perfil. `RouteProtectionTest` enumera as rotas e falha se alguma nova esquecer a
  autenticação.

### Como foram tratados os IDs inválidos?

Na auditoria da Fase 6, `/api/clients/abc` chegava ao PostgreSQL como comparação com
`bigint` e devolvia 500. Com `APP_DEBUG` ligado, a resposta mostrava SQL e stack trace.
A correção foi restringir os parâmetros de rota a números de até 18 dígitos
(`Route::patterns` em `routes/api.php`): a rota não casa e a resposta é 404, sem
chegar ao banco. Um teste de regressão cobre casos como `abc`, `1.5`, `-1` e um
número de 20 dígitos.

### Como foram tratados os N+1?

De duas formas:
- **Em desenvolvimento e testes:** o `Model::shouldBeStrict()` transforma lazy loading
  em listas em erro.
- **Regressões:** o `QueryBudgetTest` mede cada listagem com 5 e com 15 registros e
  exige o mesmo número de queries.

O dashboard faz 10 queries fixas, com agregações `FILTER` no banco, sem carregar
registros em PHP.

### Que outras medidas de segurança existem?

- **Rate limit:** 5 tentativas de login por minuto (e-mail + IP) e 180 requisições por
  minuto por usuário.
- **Mass assignment:** campos controlados pelo sistema (status, protocolo, comissão,
  perfil) são proibidos ou ignorados, e há teste para isso.
- **E-mail:** normalizado para minúsculas, já que o PostgreSQL diferencia maiúsculas.
- **Headers:** o nginx não expõe a versão e envia headers de segurança.
- **Erros:** mensagens padronizadas em português, sem detalhes internos quando
  `APP_DEBUG=false`.
- **Auditoria:** imutável.

### Qual foi o bug mais interessante encontrado?

O de fuso horário. O Laravel gravava timestamps sem offset no fuso de São Paulo, e a
sessão do PostgreSQL os interpretava como UTC: todo `timestamptz` ficava 3 horas
errado. Ele apareceu ao conferir o `created_at` na resposta do login. A correção foi
uma linha na configuração da conexão (`timezone` = `APP_TIMEZONE`), com um teste que
compara o instante gravado com o relógio. O teste passa com a correção e falha sem ela.

### Como está a estratégia de testes?

- **Backend (PHPUnit sobre PostgreSQL):** constraints, regras de negócio, cada
  endpoint, autorização, mass assignment e orçamento de queries.
- **Frontend (Vitest):**
  - o cliente HTTP (401, 419, 422);
  - os guards;
  - formulários, listagens e ações de status;
  - a acessibilidade (foco e rótulos).

A integração entre as duas camadas foi validada manualmente no navegador, pelo nginx.
Não há testes E2E automatizados.

## Limitações e próximos passos

### Quais limitações ainda existem?

- Não há carteira por corretor: todos os perfis veem todos os registros (decisão de escopo).
- A busca por texto usa `ILIKE '%termo%'`, sem índice; em volume maior, o caminho seria `pg_trgm`.
- Não há recuperação de senha por e-mail.
- Não há testes E2E automatizados; acessibilidade e responsividade foram verificadas manualmente.
- No Docker Desktop do Windows, as respostas levam ~0,2 s, com picos por causa do bind mount (WSL2 resolve).
- Num clone novo, o `scheduler` reinicia até o `composer install` terminar.
- Os dados de seed não têm histórico de auditoria.

### O que faria a seguir?

Nada que precise ser inventado agora. Os próximos passos naturais saem das
limitações: carteira por corretor, `pg_trgm` para busca, testes E2E automatizados e
recuperação de senha.
