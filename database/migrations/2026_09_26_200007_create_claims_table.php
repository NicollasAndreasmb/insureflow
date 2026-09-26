<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sinistros comunicados sobre uma apólice.
 *
 * Não há client_id: o cliente é sempre o titular da apólice (claims -> policies
 * -> clients). Guardá-lo aqui permitiria um sinistro apontar para um cliente
 * diferente do dono da apólice.
 *
 * As constraints de approved_amount mantêm o valor coerente com a decisão:
 * aprovado exige valor, negado não pode ter valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('protocol', 20)->unique();
            $table->foreignId('policy_id')->index()->constrained()->restrictOnDelete();
            $table->date('occurred_on');
            $table->date('reported_on');
            $table->text('description');
            $table->decimal('estimated_amount', 12, 2);
            $table->decimal('approved_amount', 12, 2)->nullable();
            $table->enum('status', ['open', 'under_review', 'pending_documents', 'approved', 'denied', 'closed'])
                ->default('open')
                ->index();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('ALTER TABLE claims ADD CONSTRAINT claims_reported_after_occurrence CHECK (reported_on >= occurred_on)');
        DB::statement('ALTER TABLE claims ADD CONSTRAINT claims_estimated_amount_non_negative CHECK (estimated_amount >= 0)');
        DB::statement('ALTER TABLE claims ADD CONSTRAINT claims_approved_amount_non_negative CHECK (approved_amount >= 0)');
        DB::statement("ALTER TABLE claims ADD CONSTRAINT claims_approved_requires_amount CHECK (status <> 'approved' OR approved_amount IS NOT NULL)");
        DB::statement("ALTER TABLE claims ADD CONSTRAINT claims_denied_has_no_amount CHECK (status <> 'denied' OR approved_amount IS NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
