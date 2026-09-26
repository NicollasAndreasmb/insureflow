<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurers', function (Blueprint $table) {
            $table->id();
            $table->string('legal_name', 200);
            $table->string('trade_name', 150)->index();
            $table->char('cnpj', 14);
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
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
        });

        DB::statement("ALTER TABLE insurers ADD CONSTRAINT insurers_cnpj_format CHECK (cnpj ~ '^[0-9]{14}$')");
        DB::statement("ALTER TABLE insurers ADD CONSTRAINT insurers_zip_code_format CHECK (zip_code ~ '^[0-9]{8}$')");
        DB::statement("ALTER TABLE insurers ADD CONSTRAINT insurers_state_format CHECK (state ~ '^[A-Z]{2}$')");
        DB::statement('CREATE UNIQUE INDEX insurers_cnpj_unique ON insurers (cnpj) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('insurers');
    }
};
