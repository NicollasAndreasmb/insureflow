#!/bin/sh
# Executado pelo entrypoint oficial do PostgreSQL apenas na primeira inicialização
# do volume. Cria o banco `<DB>_test`, usado pelo PHPUnit, para que os testes rodem
# no mesmo SGBD da aplicação (constraints, jsonb) sem tocar nos dados de desenvolvimento.
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE "${POSTGRES_DB}_test" OWNER "$POSTGRES_USER";
EOSQL
