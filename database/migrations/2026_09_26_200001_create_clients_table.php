<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes da corretora (pessoas físicas e jurídicas).
 *
 * O documento é gravado apenas com dígitos e a constraint garante que o tamanho
 * corresponda ao tipo de pessoa (CPF = 11, CNPJ = 14). A unicidade ignora
 * registros excluídos (soft delete) para permitir recadastrar um cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->enum('person_type', ['individual', 'company']);
            $table->string('name', 150)->index();
            $table->string('document', 14);
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->char('zip_code', 8)->nullable();
            $table->string('street')->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('state', 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['state', 'city']);
        });

        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_document_matches_person_type CHECK (
            (person_type = 'individual' AND document ~ '^[0-9]{11}$')
            OR (person_type = 'company' AND document ~ '^[0-9]{14}$')
        )");
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_zip_code_format CHECK (zip_code ~ '^[0-9]{8}$')");
        DB::statement("ALTER TABLE clients ADD CONSTRAINT clients_state_format CHECK (state ~ '^[A-Z]{2}$')");
        DB::statement('CREATE UNIQUE INDEX clients_document_unique ON clients (document) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
