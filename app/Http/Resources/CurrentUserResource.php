<?php

namespace App\Http\Resources;

use App\Models\Claim;
use App\Models\Client;
use App\Models\Coverage;
use App\Models\InsuranceProduct;
use App\Models\Insurer;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Usuário autenticado acrescido das permissões calculadas pelas Policies.
 *
 * O frontend usa este mapa apenas para decidir o que exibir (botões, menus),
 * sem reimplementar a matriz de perfis. A autorização real continua sendo
 * verificada pelo backend em cada requisição.
 */
class CurrentUserResource extends UserResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'permissions' => [
                'clients.create' => $this->can('create', Client::class),
                'clients.delete' => $this->can('delete', new Client),
                'insurers.manage' => $this->can('create', Insurer::class),
                'insurance_products.manage' => $this->can('create', InsuranceProduct::class),
                'policies.manage' => $this->can('create', Policy::class),
                'policies.delete' => $this->can('delete', new Policy),
                'coverages.manage' => $this->can('create', Coverage::class),
                'claims.manage' => $this->can('create', Claim::class),
                'claims.delete' => $this->can('delete', new Claim),
                'users.manage' => $this->can('create', User::class),
            ],
        ];
    }
}
