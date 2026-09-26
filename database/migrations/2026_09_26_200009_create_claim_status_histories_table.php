<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Histórico imutável das mudanças de status de cada sinistro.
 *
 * from_status é nulo no registro de abertura. user_id usa SET NULL para que o
 * histórico sobreviva mesmo se um usuário for removido fisicamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $statuses = ['open', 'under_review', 'pending_documents', 'approved', 'denied', 'closed'];

        Schema::create('claim_status_histories', function (Blueprint $table) use ($statuses) {
            $table->id();
            $table->foreignId('claim_id')->index()->constrained()->cascadeOnDelete();
            $table->enum('from_status', $statuses)->nullable();
            $table->enum('to_status', $statuses);
            $table->foreignId('user_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER claim_status_histories_append_only
                BEFORE UPDATE ON claim_status_histories
                FOR EACH ROW EXECUTE FUNCTION prevent_row_update();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_status_histories');
    }
};
