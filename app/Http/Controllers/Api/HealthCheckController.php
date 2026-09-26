<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Verificação de saúde da API consumida pelo frontend e por ferramentas de monitoramento.
 *
 * Diferente da rota /up nativa do Laravel (que só confirma que o framework
 * inicializou), este endpoint também valida a conexão com o PostgreSQL,
 * permitindo diagnosticar rapidamente se o ambiente Docker está completo.
 */
class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $databaseIsReachable = $this->databaseIsReachable();

        return response()->json([
            'status' => $databaseIsReachable ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'database' => $databaseIsReachable ? 'connected' : 'unreachable',
            'timestamp' => now()->toIso8601String(),
        ], $databaseIsReachable ? 200 : 503);
    }

    private function databaseIsReachable(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
