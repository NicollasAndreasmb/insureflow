<?php

namespace App\Http\Controllers\Api;

use App\Enums\ClaimStatus;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PersonType;
use App\Enums\PolicyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Support\BrazilianStates;
use BackedEnum;
use Illuminate\Http\JsonResponse;

/**
 * Listas de valores (enums e UFs) com seus rótulos, para selects e filtros do
 * frontend. Os enums do backend são a única fonte desses valores.
 */
class OptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'person_types' => $this->options(PersonType::cases()),
            'payment_methods' => $this->options(PaymentMethod::cases()),
            'policy_statuses' => $this->options(PolicyStatus::cases()),
            'installment_statuses' => $this->options(InstallmentStatus::cases()),
            'claim_statuses' => $this->options(ClaimStatus::cases()),
            'user_roles' => $this->options(UserRole::cases()),
            'states' => BrazilianStates::CODES,
        ]]);
    }

    /**
     * @param  list<BackedEnum>  $cases
     */
    private function options(array $cases): array
    {
        return array_map(fn (BackedEnum $case) => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
