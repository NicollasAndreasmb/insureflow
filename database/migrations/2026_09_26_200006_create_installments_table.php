<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Parcelas do prêmio de uma apólice.
 *
 * O status "atrasado" não é persistido (é derivado de pending + vencimento
 * passado), por isso a CHECK aceita apenas pending, paid e cancelled. A soma
 * das parcelas igual ao prêmio não pode ser expressa em uma CHECK (envolve
 * várias linhas); ela é garantida por App\Support\InstallmentSchedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('number');
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->date('paid_on')->nullable();
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestampsTz();

            $table->unique(['policy_id', 'number']);
            $table->index(['status', 'due_date']);
        });

        DB::statement('ALTER TABLE installments ADD CONSTRAINT installments_number_positive CHECK (number >= 1)');
        DB::statement('ALTER TABLE installments ADD CONSTRAINT installments_amount_positive CHECK (amount > 0)');
        DB::statement("ALTER TABLE installments ADD CONSTRAINT installments_paid_on_matches_status CHECK ((status = 'paid') = (paid_on IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
