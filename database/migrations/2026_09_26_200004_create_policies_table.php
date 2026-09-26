<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Apólices: entidade central do domínio.
 *
 * Todas as FKs usam RESTRICT porque uma apólice é um registro contratual e
 * financeiro: clientes, seguradoras, modalidades e corretores com apólices
 * vinculadas só podem ser inativados ou excluídos logicamente.
 *
 * commission_amount é uma coluna gerada pelo PostgreSQL a partir do prêmio e da
 * taxa. Dessa forma os dois valores nunca divergem, independentemente de quem
 * grava a apólice (API, seeders, SQL manual). O banco rejeita escrita direta nela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50);
            $table->foreignId('client_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('insurer_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('insurance_product_id')->index()->constrained()->restrictOnDelete();
            $table->foreignId('broker_id')->index()->constrained('users')->restrictOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('premium', 12, 2);
            $table->decimal('deductible', 12, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->storedAs('round(premium * commission_rate / 100, 2)');
            $table->enum('payment_method', ['bank_slip', 'credit_card', 'direct_debit', 'pix']);
            $table->smallInteger('installments_count')->default(1);
            $table->enum('status', ['quote', 'pending', 'active', 'suspended', 'cancelled', 'expired'])->default('quote');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['status', 'ends_on']);
        });

        DB::statement('ALTER TABLE policies ADD CONSTRAINT policies_valid_term CHECK (ends_on > starts_on)');
        DB::statement('ALTER TABLE policies ADD CONSTRAINT policies_premium_non_negative CHECK (premium >= 0)');
        DB::statement('ALTER TABLE policies ADD CONSTRAINT policies_deductible_non_negative CHECK (deductible >= 0)');
        DB::statement('ALTER TABLE policies ADD CONSTRAINT policies_commission_rate_range CHECK (commission_rate BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE policies ADD CONSTRAINT policies_installments_count_range CHECK (installments_count BETWEEN 1 AND 12)');
        DB::statement('CREATE UNIQUE INDEX policies_insurer_number_unique ON policies (insurer_id, number) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};
