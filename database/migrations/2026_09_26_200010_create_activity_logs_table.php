<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trilha de auditoria polimórfica: registra operações sobre qualquer entidade.
 *
 * subject_type guarda o alias do morph map (ex.: "policy"), não o nome da
 * classe, para que refatorações de namespace não invalidem o histórico.
 * changes (jsonb) guarda {"old": {...}, "new": {...}} apenas dos campos alterados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->morphs('subject');
            $table->enum('action', ['created', 'updated', 'deleted', 'restored', 'status_changed']);
            $table->jsonb('changes')->nullable();
            $table->timestampTz('created_at')->useCurrent()->index();
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER activity_logs_append_only
                BEFORE UPDATE ON activity_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_row_update();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
