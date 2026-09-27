# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/);
o projeto segue [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## 1.0.0 - 2026-09-26

Primeira versão pública.

### Funcionalidades

- **Autenticação**: login por sessão com Laravel Sanctum (cookie HttpOnly + CSRF),
  logout, bloqueio de contas desativadas e limite de tentativas de login.
- **Perfis e permissões**: administrador, corretor e assistente, com autorização por
  Policies no backend e permissões expostas para a SPA.
- **Cadastros**: clientes (PF/PJ com CPF/CNPJ validado), seguradoras, modalidades e
  usuários, com exclusão lógica.
- **Apólices**: vigência, prêmio, franquia, forma de pagamento, comissão calculada pelo
  banco, coberturas e transições de status controladas (incluindo condições de
  vigência).
- **Parcelas**: geração a partir do prêmio com arredondamento na 1ª parcela, registro
  de pagamento e atraso derivado.
- **Sinistros**: protocolo sequencial por ano, abertura limitada à vigência, fluxo de
  análise/aprovação/negação/encerramento e histórico de status imutável.
- **Dashboard** com indicadores da carteira calculados no banco.
- **Auditoria** automática e imutável das operações.
- **Expiração automática** diária das apólices vencidas (`policies:expire`).

### Qualidade

- 208 testes de backend (1.150 asserções, sobre PostgreSQL) e 59 testes de frontend.
- Invariantes garantidas por constraints, coluna gerada, índices únicos parciais e
  trigger append-only no PostgreSQL.
- Ambiente completo em Docker (nginx, PHP-FPM, scheduler, PostgreSQL 17 e Node).

### Limitações conhecidas

Listadas em [docs/architecture.md](docs/architecture.md#limitações-conhecidas).
