<?php

namespace App\Providers;

use App\Models\Claim;
use App\Models\Client;
use App\Models\Coverage;
use App\Models\Installment;
use App\Models\InsuranceProduct;
use App\Models\Insurer;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * O morph map faz relações polimórficas (activity_logs.subject_type) gravarem
     * aliases estáveis em vez de nomes de classe, e enforce impede que um model
     * não mapeado seja gravado por engano.
     *
     * O modo estrito fora de produção expõe cedo lazy loading (N+1), atributos
     * não carregados e mass assignment silenciosamente descartado.
     *
     * O limite de login é por e-mail + IP, para dificultar força bruta sem
     * bloquear outros usuários atrás do mesmo IP (ex.: rede do escritório).
     * As rotas autenticadas têm um limite por usuário folgado para o uso da
     * interface, mas que impede varreduras em massa com uma sessão válida.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'client' => Client::class,
            'insurer' => Insurer::class,
            'insurance_product' => InsuranceProduct::class,
            'policy' => Policy::class,
            'coverage' => Coverage::class,
            'installment' => Installment::class,
            'claim' => Claim::class,
        ]);

        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(180)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
