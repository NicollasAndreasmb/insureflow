<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Autenticação da SPA via Sanctum em modo stateful (sessão + cookie HttpOnly).
 *
 * Fluxo esperado do frontend: GET /sanctum/csrf-cookie -> POST /api/login ->
 * requisições autenticadas enviando o cookie de sessão e o header X-XSRF-TOKEN.
 * A sessão é regenerada no login (evita session fixation) e invalidada no logout.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        $user = $request->authenticate();

        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
