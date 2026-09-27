# Domínio e regras de negócio

## Entidades

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

| Entidade | O que representa |
|---|---|
| Cliente (`clients`) | Pessoa física ou jurídica atendida pela corretora. CPF/CNPJ validado por dígito verificador e gravado só com dígitos. |
| Seguradora (`insurers`) | Companhia que emite as apólices. |
| Modalidade (`insurance_products`) | Tipo de seguro (Auto, Vida...). Nova modalidade é só um registro, sem mudar código. |
| Apólice (`policies`) | Entidade central: contrato entre cliente e seguradora, com vigência, prêmio, comissão, corretor responsável e status. |
| Cobertura (`coverages`) | Garantia contratada em uma apólice, com limite, franquia e prêmio adicional. |
| Parcela (`installments`) | Divisão do prêmio em pagamentos mensais. |
| Sinistro (`claims`) | Ocorrência comunicada sobre uma apólice, com protocolo e fluxo de análise. |
| Usuário (`users`) | Pessoa com acesso ao sistema: administrador, corretor ou assistente. |
| Auditoria (`activity_logs`, `claim_status_histories`) | Registros imutáveis do que aconteceu, por quem e quando. |

Clientes, seguradoras, modalidades, apólices, sinistros e usuários usam exclusão
lógica (soft delete). Coberturas e parcelas pertencem à apólice e não são excluídas
de forma independente dela.

## Apólices

**Status e transições** (fonte: `App\Enums\PolicyStatus`):

```
cotação ──> pendente ──> ativa <──> suspensa
                           │           │
                           └─> expirada <┘
qualquer status não final ──> cancelada
```

- Uma apólice nasce como cotação, pendente ou ativa (ativa quando é cadastrada já
  emitida pela seguradora). Os demais status só são alcançados por transição.
- Cancelada e expirada são estados finais. Uma renovação é uma nova apólice.
- Para ficar ativa, a vigência não pode ter terminado. Para expirar, precisa ter
  terminado (`Policy::termAllows()`). As transições oferecidas pela API
  (`allowed_transitions`) já combinam as duas condições: uma apólice ativa só
  mostra "expirar" depois do fim da vigência.
- Apólices em estado final só aceitam alteração de observações. Somente cotações
  podem ser excluídas; as demais são canceladas.

**Parcelas** (fonte: `App\Support\InstallmentSchedule` e `App\Services\PolicyService`):

- São geradas quando a apólice deixa de ser cotação. Cotações não têm cobrança.
- O prêmio é dividido em centavos inteiros e a diferença de arredondamento vai para
  a 1ª parcela, então a soma é sempre exatamente o prêmio
  (R$ 1.000,00 em 3x = 333,34 + 333,33 + 333,33).
- Os vencimentos são mensais a partir do início da vigência, sem "transbordar" o
  mês (31/01 → 28/02 → 31/03).
- Alterar prêmio, quantidade de parcelas ou início da vigência regenera as parcelas
  se nenhuma foi paga; se alguma já foi paga, a alteração é recusada.
- Cancelar a apólice cancela as parcelas pendentes. As pagas permanecem.

**Comissão**: `commission_amount = round(premium × commission_rate / 100, 2)`,
calculada pelo PostgreSQL como coluna gerada. Não há como gravar um valor
divergente, nem pela API nem por SQL manual.

## Parcelas

- Status gravados: pendente, pago, cancelado.
- **Atrasada** não é gravada: é o status efetivo de uma parcela pendente com
  vencimento anterior a hoje. A API devolve o status efetivo nas respostas e nos
  filtros, então a informação nunca depende de um job ter rodado.
- Só parcelas pendentes (inclusive atrasadas) podem ser pagas, com data de
  pagamento até hoje. Parcela paga sempre tem data de pagamento, e só ela tem
  (constraint no banco).

## Sinistros

**Status e transições** (fonte: `App\Enums\ClaimStatus`):

```
aberto ──> em análise <──> documentação pendente
               │                   │
               └──> aprovado / negado <┘
                          │
                          └──> encerrado
```

- Só pode ser aberto para uma apólice **ativa**, ou **expirada** quando a ocorrência
  aconteceu durante a vigência. A data de ocorrência deve estar dentro da vigência
  e não pode ser futura; a comunicação não pode ser anterior à ocorrência.
- O protocolo é sequencial por ano de comunicação (`SIN-2026-000042`). A geração usa
  um advisory lock do PostgreSQL para que aberturas simultâneas não repitam números.
- Toda mudança de status grava uma linha em `claim_status_histories` (com usuário e
  observações). Aprovar exige valor aprovado; negar remove o valor.
- Sinistros encerrados não podem ser editados. "Em aberto", no dashboard e nos
  filtros, significa "ainda não encerrado".

## Exclusões

Clientes, seguradoras e modalidades com apólices em andamento (não canceladas nem
expiradas) não podem ser excluídos, apenas inativados. Registros inativos
continuam visíveis, mas não podem receber novas apólices.

## Expiração automática

O comando `php artisan policies:expire` roda diariamente às 00:05, no container
`scheduler`. Ele expira apólices ativas ou suspensas cuja vigência terminou antes de
hoje, pelas mesmas regras de transição da API. É idempotente: rodar mais de uma vez
não altera apólices já expiradas. Cada expiração é auditada com usuário nulo
(origem: sistema).

## Auditoria

- Criação, alteração, exclusão, restauração e mudança de status das entidades de
  negócio são gravadas automaticamente em `activity_logs` pelos eventos dos models,
  com o usuário autenticado (ou nulo, quando a origem é o sistema).
- Só os campos alterados são guardados (`{old, new}`). Senhas, tokens e timestamps
  técnicos nunca entram no log.
- `activity_logs` e `claim_status_histories` são somente inserção: um trigger do
  PostgreSQL rejeita qualquer `UPDATE`, e a API não expõe escrita nem exclusão.
- A auditoria de usuários só é visível para administradores.

## Onde cada regra é garantida

| Regra | Garantida por |
|---|---|
| Valores não negativos, vigência válida, formato de CPF/CNPJ/UF/CEP | CHECK constraints do PostgreSQL |
| Comissão coerente com prêmio e taxa | Coluna gerada |
| Parcela paga ⇔ data de pagamento; aprovado ⇒ valor; negado ⇒ sem valor | CHECK constraints |
| Unicidade de documento, e-mail e número da apólice (por seguradora) | Índices únicos parciais que ignoram registros excluídos |
| Históricos imutáveis | Trigger `prevent_row_update()` + trait `AppendOnly` |
| Transições de status | Enums (`allowedTransitions`) + `Policy::termAllows()` (vigência) + Services |
| Datas relativas ao status, elegibilidade de sinistro, parcelas | `PolicyService`, `ClaimService` |
| Soma das parcelas = prêmio | `InstallmentSchedule` (não expressável em CHECK) + teste de integridade |
