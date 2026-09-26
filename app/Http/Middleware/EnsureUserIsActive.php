<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encerra a sessão de um usuário desativado depois de ter feito login.
 *
 * O login já recusa contas inativas, mas uma sessão aberta continuaria válida
 * até expirar. Aplicado a todas as rotas autenticadas, faz a desativação ter
 * efeito na requisição seguinte.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json(['message' => __('http.inactive_account')], 403);
        }

        return $next($request);
    }
}
