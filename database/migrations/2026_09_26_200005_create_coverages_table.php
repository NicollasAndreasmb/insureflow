<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coberturas contratadas em uma apólice (relação 1:N).
 *
 * Coberturas não existem fora da apólice, por isso usam CASCADE e não têm soft
 * delete próprio: ao excluir logicamente a apólice elas deixam de ser exibidas
 * junto com ela, e alterações individuais ficam registradas em activity_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('coverage_limit', 12, 2);
            $table->decimal('deductible', 12, 2)->nullable();
            $table->decimal('additional_premium', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['policy_id', 'name']);
        });

        DB::statement('ALTER TABLE coverages ADD CONSTRAINT coverages_limit_non_negative CHECK (coverage_limit >= 0)');
        DB::statement('ALTER TABLE coverages ADD CONSTRAINT coverages_deductible_non_negative CHECK (deductible >= 0)');
        DB::statement('ALTER TABLE coverages ADD CONSTRAINT coverages_additional_premium_non_negative CHECK (additional_premium >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('coverages');
    }
};
