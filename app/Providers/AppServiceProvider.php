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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

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
    }
}
