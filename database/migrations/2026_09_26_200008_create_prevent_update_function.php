<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Função de trigger compartilhada pelas tabelas de histórico (somente inserção).
 *
 * Tabelas de auditoria precisam ser imutáveis também fora da aplicação: a
 * proteção no model Eloquent não impede um UPDATE via SQL manual ou query
 * builder, então o próprio banco rejeita qualquer alteração de linha.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_row_update() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Rows in table % are append-only and cannot be updated', TG_TABLE_NAME
                    USING ERRCODE = 'restrict_violation';
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_row_update()');
    }
};
