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
use Illuminate\Validation\Rules\Password;

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
     * O login tem três limites: por e-mail + IP (força bruta em uma conta sem
     * bloquear colegas atrás do mesmo IP), por IP (um atacante testando uma
     * senha comum em vários e-mails) e por e-mail em uma hora (tentativas
     * distribuídas entre IPs). As rotas autenticadas têm um limite por usuário
     * folgado para o uso da interface, mas que impede varreduras em massa com
     * uma sessão válida; rotas públicas são limitadas por IP.
     *
     * A política de senha padrão vale para todo cadastro de senha; em produção
     * ela também recusa senhas que aparecem em vazamentos conhecidos.
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

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('email-ip|'.$email.'|'.$request->ip()),
                Limit::perMinute(20)->by('ip|'.$request->ip()),
                Limit::perHour(30)->by('email|'.$email),
            ];
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(180)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        Password::defaults(function () {
            $rule = Password::min(10)->max(72)->letters()->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
