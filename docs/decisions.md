# Decisões técnicas

Cada decisão segue o formato **problema → decisão → motivo → consequência**. Todas
foram tomadas e implementadas durante o desenvolvimento; os detalhes de
implementação estão em [architecture.md](architecture.md).

## 1. Monólito Laravel + SPA Vue no mesmo repositório

- **Problema:** separar frontend e backend sem multiplicar a infraestrutura.
- **Decisão:** uma aplicação Laravel que expõe `/api/*` e entrega a view que carrega
  a SPA; o Vue vive em `resources/js`.
- **Motivo:** mesmo domínio significa autenticação por sessão sem CORS nem tokens, um
  único deploy e um único repositório para um projeto deste porte.
- **Consequência:** frontend e backend se comunicam apenas pela API, então a
  separação continua existindo. Separá-los no futuro exigiria configurar CORS e
  domínios do Sanctum.

## 2. Sanctum em modo SPA (sessão + CSRF)

- **Problema:** autenticar a SPA sem expor credenciais ao JavaScript.
- **Decisão:** sessão do Laravel em cookie HttpOnly, com o cookie `XSRF-TOKEN` para
  CSRF; nada é salvo em `localStorage`.
- **Motivo:** um token em `localStorage` pode ser lido por qualquer script injetado
  (XSS); um cookie HttpOnly, não.
- **Consequência:** o login exige buscar `/sanctum/csrf-cookie` antes, e o domínio
  precisa estar em `SANCTUM_STATEFUL_DOMAINS`. Os testes simulam requisições da SPA
  para passar pelo mesmo pipeline.

## 3. PostgreSQL como guardião das invariantes

- **Problema:** regras validadas só na aplicação podem ser burladas por seeders,
  scripts ou SQL manual.
- **Decisão:** CHECK constraints, índices únicos parciais (`WHERE deleted_at IS NULL`),
  coluna gerada e triggers, cobertos por testes que tentam gravar dados inválidos.
- **Motivo:** o PostgreSQL oferece esses recursos de forma nativa e confiável, e o banco
  é a última linha de defesa.
- **Consequência:** migrations com SQL específico do PostgreSQL (o projeto não é
  portátil para outro banco), e testes rodam em PostgreSQL, não em SQLite.

## 4. Comissão como coluna gerada

- **Problema:** `commission_amount` depende de `premium` e `commission_rate`, e um
  valor gravado à parte poderia divergir.
- **Decisão:** `commission_amount GENERATED ALWAYS AS (round(premium * commission_rate / 100, 2)) STORED`.
- **Motivo:** o banco garante a coerência para qualquer escrita, e o valor continua
  armazenado (consultável e somável no dashboard).
- **Consequência:** a coluna não pode ser escrita (a API a ignora; o banco recusa SQL
  direto), e o model a relê após salvar (`#[Refreshes]`).

## 5. Transições de status nos enums

- **Problema:** as regras de "de qual status para qual" ficariam espalhadas por
  controllers, requests e frontend.
- **Decisão:** `PolicyStatus` e `ClaimStatus` definem `allowedTransitions()`; condições
  que dependem de datas ficam em `Policy::termAllows()`; os Services aplicam os
  efeitos colaterais (gerar ou cancelar parcelas, gravar histórico).
- **Motivo:** uma única fonte para a máquina de estados, testável isoladamente.
- **Consequência:** toda mudança de status passa por rotas próprias
  (`PATCH .../status`); o formulário comum recusa o campo `status`.

## 6. A API diz o que é possível; o frontend não replica regras

- **Problema:** para mostrar só os botões válidos, o Vue precisaria conhecer
  transições, estados finais e a matriz de perfis, duplicando o backend.
- **Decisão:** os resources expõem `allowed_transitions`, `is_editable`,
  `is_deletable` e `is_payable`; `/api/user` expõe `permissions`; `/api/options`
  expõe rótulos de enums, entidades e campos.
- **Motivo:** uma regra alterada no backend reflete no frontend sem mudança no Vue.
- **Consequência:** alguns campos a mais nas respostas, calculados pelos mesmos
  métodos que os Services usam para validar.

## 7. Status "atrasado" derivado, nunca gravado

- **Problema:** gravar o atraso exigiria um job diário, e o dado ficaria errado sempre
  que o job falhasse ou atrasasse.
- **Decisão:** o banco guarda pendente/pago/cancelado; "atrasado" é calculado
  (pendente e vencimento passado) no model, nos filtros e no dashboard.
- **Motivo:** a informação é sempre exata no momento da consulta.
- **Consequência:** o enum tem o caso `overdue`, mas a constraint do banco o recusa; a
  API devolve o status efetivo.

## 8. Auditoria automática e somente inserção

- **Problema:** registrar operações manualmente em cada Service é fácil de esquecer, e
  um log alterável não serve como auditoria.
- **Decisão:** o trait `HasActivityLog` grava `activity_logs` pelos eventos do Eloquent;
  um trigger do PostgreSQL rejeita qualquer `UPDATE` em `activity_logs` e
  `claim_status_histories`; a API não tem rotas de escrita para eles.
- **Motivo:** cobertura automática de toda entidade de negócio e imutabilidade
  garantida fora da aplicação.
- **Consequência:** atualizações em massa via query builder não geram log (usadas só
  para efeitos já registrados na entidade principal); os dados de seed não têm
  histórico.

## 9. Policies para "quem pode", Services para "quando pode"

- **Problema:** misturar perfil e estado do registro na autorização deixa as regras
  difíceis de enxergar.
- **Decisão:** as Policies aplicam uma matriz de perfis simples; regras de estado
  (excluir só cotações, editar só o que não é final) ficam nos Services e models.
- **Motivo:** a matriz de perfis fica legível e testável por completo
  (`AuthorizationMatrixTest`).
- **Consequência:** uma operação pode passar na Policy e ser recusada com 422 pela
  regra de negócio, com mensagem explicando o motivo.

## 10. Services apenas onde há regra de negócio

- **Problema:** criar um Service por CRUD gera camadas vazias.
- **Decisão:** só `PolicyService`, `ClaimService` e `DashboardService` existem; os CRUDs
  simples usam o model no controller.
- **Motivo:** cada camada precisa justificar o próprio custo.
- **Consequência:** se um CRUD ganhar regras, o Service é criado nesse momento.

## 11. Form Requests e API Resources como contrato

- **Problema:** validação e formato de resposta espalhados pelos controllers.
- **Decisão:** toda entrada passa por Form Requests (que normalizam documentos,
  telefones e e-mails, e autorizam antes de validar); toda saída passa por Resources
  (enums como `{value, label}`, dinheiro como string).
- **Motivo:** o contrato da API fica explícito e os controllers ficam finos.
- **Consequência:** controllers usam apenas `validated()`; campos não previstos
  simplesmente não chegam ao model (proteção contra mass assignment).

## 12. `node_modules` em volume Docker e ajustes para Windows

- **Problema:** no Docker Desktop, ler milhares de arquivos pelo bind mount deixava o
  Vitest em 167 s (com timeouts), o Vite lento e, com o polling do Vite, a API com 4 a
  10 s por requisição.
- **Decisão:** `node_modules` em volume nomeado; Vite vigiando só `resources/` e
  `routes/` a cada 1 s; opcache revalidando a cada 2 s; varredura do Tailwind
  restrita a `resources/`.
- **Motivo:** tirar do bind mount o que não precisa estar nele, sem mudar a arquitetura.
- **Consequência:** Vitest em ~5 s, API em ~0,2 s; `node_modules` não aparece na pasta
  do projeto, e uma edição de PHP pode levar até 2 s para valer.

## 13. Identificadores de rota restritos a números

- **Problema:** `/api/clients/abc` chegava ao PostgreSQL como comparação com `bigint` e
  gerava erro 500 (com SQL e trace visíveis em modo debug).
- **Decisão:** parâmetros de rota aceitam apenas números de até 18 dígitos.
- **Motivo:** a entrada inválida é barrada no roteamento, antes de qualquer query.
- **Consequência:** esses casos respondem 404; `RouteProtectionTest` protege contra
  regressão.
