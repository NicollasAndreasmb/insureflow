<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modalidades de seguro (Auto, Residencial, Vida...).
 *
 * Modalidades são dados, não estrutura: uma nova modalidade é apenas um novo
 * registro, sem migrations ou código novo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement('CREATE UNIQUE INDEX insurance_products_name_unique ON insurance_products (name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX insurance_products_slug_unique ON insurance_products (slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_products');
    }
};
