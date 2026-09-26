<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityAction;
use App\Enums\ClaimStatus;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PersonType;
use App\Enums\PolicyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Support\BrazilianStates;
use BackedEnum;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Listas de valores e rótulos para selects, filtros e exibição no frontend.
 *
 * Os enums do backend e os arquivos de tradução (lang/pt_BR) são a única fonte
 * desses textos: entidades auditáveis vêm do morph map e os nomes de campos
 * reutilizam os atributos de validação, para que o Vue não mantenha cópias.
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
            'activity_actions' => $this->options(ActivityAction::cases()),
            'activity_subject_types' => array_map(
                fn (string $alias) => ['value' => $alias, 'label' => __("entities.{$alias}")],
                array_keys(Relation::morphMap()),
            ),
            'field_labels' => collect(trans('validation.attributes'))
                ->reject(fn (string $label, string $field) => Str::contains($field, '*'))
                ->all(),
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
