<?php

namespace Tests\Feature\Database;

use App\Enums\ClaimStatus;
use App\Enums\InstallmentStatus;
use App\Enums\PersonType;
use App\Models\Claim;
use App\Models\ClaimStatusHistory;
use App\Models\Client;
use App\Models\Coverage;
use App\Models\Installment;
use App\Models\Insurer;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/**
 * Garante que as invariantes de negócio são aplicadas pelo próprio PostgreSQL,
 * independentemente da camada de aplicação.
 *
 * Cada violação roda dentro de DB::transaction(), que vira um savepoint dentro
 * da transação do RefreshDatabase: sem isso, o primeiro erro abortaria a
 * transação inteira do PostgreSQL e as asserções seguintes falhariam.
 */
class DatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_term_must_end_after_it_starts(): void
    {
        $this->assertViolates('policies_valid_term', fn () => Policy::factory()->create([
            'starts_on' => '2026-05-10',
            'ends_on' => '2026-05-10',
        ]));
    }

    public function test_policy_money_and_ranges_are_validated(): void
    {
        $this->assertViolates('policies_premium_non_negative', fn () => Policy::factory()->create(['premium' => '-0.01']));
        $this->assertViolates('policies_deductible_non_negative', fn () => Policy::factory()->create(['deductible' => '-1.00']));
        $this->assertViolates('policies_commission_rate_range', fn () => Policy::factory()->create(['commission_rate' => '100.01']));
        $this->assertViolates('policies_installments_count_range', fn () => Policy::factory()->create(['installments_count' => 13]));
        $this->assertViolates('policies_installments_count_range', fn () => Policy::factory()->create(['installments_count' => 0]));
    }

    public function test_status_columns_reject_unknown_values(): void
    {
        $policy = Policy::factory()->create();
        $user = User::factory()->create();

        $this->assertViolates('policies_status_check', fn () => DB::table('policies')->where('id', $policy->id)->update(['status' => 'draft']));
        $this->assertViolates('users_role_check', fn () => DB::table('users')->where('id', $user->id)->update(['role' => 'root']));
    }

    public function test_commission_amount_is_generated_from_premium_and_rate(): void
    {
        $policy = Policy::factory()->create(['premium' => '1000.00', 'commission_rate' => '12.50']);

        $this->assertSame('125.00', $policy->commission_amount);

        $policy->update(['premium' => '2999.99']);

        $this->assertSame('375.00', $policy->commission_amount);
        $this->assertSame('375.00', $policy->fresh()->commission_amount);
    }

    public function test_commission_amount_cannot_be_written_directly(): void
    {
        $policy = Policy::factory()->create();

        $this->assertViolates('commission_amount', fn () => DB::table('policies')
            ->where('id', $policy->id)
            ->update(['commission_amount' => '1.00']));
    }

    public function test_policy_number_is_unique_per_insurer_ignoring_soft_deleted(): void
    {
        $policy = Policy::factory()->create(['number' => '01.23.4567890']);

        $this->assertViolates('policies_insurer_number_unique', fn () => Policy::factory()->create([
            'number' => '01.23.4567890',
            'insurer_id' => $policy->insurer_id,
        ]));

        Policy::factory()->create(['number' => '01.23.4567890', 'insurer_id' => Insurer::factory()]);

        $policy->delete();
        Policy::factory()->create(['number' => '01.23.4567890', 'insurer_id' => $policy->insurer_id]);

        $this->assertSame(3, Policy::withTrashed()->where('number', '01.23.4567890')->count());
    }

    public function test_client_document_must_match_person_type(): void
    {
        $this->assertViolates('clients_document_matches_person_type', fn () => Client::factory()->create([
            'person_type' => PersonType::Individual,
            'document' => '12345678000199',
        ]));

        $this->assertViolates('clients_document_matches_person_type', fn () => Client::factory()->create([
            'person_type' => PersonType::Company,
            'document' => '12345678901',
        ]));

        $this->assertViolates('clients_document_matches_person_type', fn () => Client::factory()->create([
            'document' => '123.456.789-01',
        ]));
    }

    public function test_client_document_is_unique_among_active_records(): void
    {
        $client = Client::factory()->create(['document' => '12345678901']);

        $this->assertViolates('clients_document_unique', fn () => Client::factory()->create(['document' => '12345678901']));

        $client->delete();

        $this->assertModelExists(Client::factory()->create(['document' => '12345678901']));
    }

    public function test_address_fields_follow_expected_format(): void
    {
        $this->assertViolates('clients_state_format', fn () => Client::factory()->create(['state' => 'sp']));
        $this->assertViolates('clients_zip_code_format', fn () => Client::factory()->create(['zip_code' => '01310-10']));
        $this->assertViolates('insurers_cnpj_format', fn () => Insurer::factory()->create(['cnpj' => '12.345.678/000']));
    }

    public function test_installment_payment_date_must_match_status(): void
    {
        $this->assertViolates('installments_paid_on_matches_status', fn () => Installment::factory()->create([
            'status' => InstallmentStatus::Paid,
            'paid_on' => null,
        ]));

        $this->assertViolates('installments_paid_on_matches_status', fn () => Installment::factory()->create([
            'status' => InstallmentStatus::Pending,
            'paid_on' => today(),
        ]));
    }

    public function test_overdue_is_never_persisted(): void
    {
        $this->assertViolates('installments_status_check', fn () => Installment::factory()->create([
            'status' => InstallmentStatus::Overdue,
        ]));
    }

    public function test_installment_amount_and_number_rules(): void
    {
        $installment = Installment::factory()->create(['number' => 1]);

        $this->assertViolates('installments_amount_positive', fn () => Installment::factory()->create(['amount' => '0.00']));
        $this->assertViolates('installments_number_positive', fn () => Installment::factory()->create(['number' => 0]));
        $this->assertViolates('installments_policy_id_number_unique', fn () => Installment::factory()->create([
            'policy_id' => $installment->policy_id,
            'number' => 1,
        ]));
    }

    public function test_coverage_values_cannot_be_negative(): void
    {
        $this->assertViolates('coverages_limit_non_negative', fn () => Coverage::factory()->create(['coverage_limit' => '-1.00']));
        $this->assertViolates('coverages_additional_premium_non_negative', fn () => Coverage::factory()->create(['additional_premium' => '-1.00']));
    }

    public function test_claim_dates_and_amounts_are_coherent(): void
    {
        $this->assertViolates('claims_reported_after_occurrence', fn () => Claim::factory()->create([
            'occurred_on' => '2026-05-10',
            'reported_on' => '2026-05-09',
        ]));

        $this->assertViolates('claims_approved_requires_amount', fn () => Claim::factory()->create([
            'status' => ClaimStatus::Approved,
            'approved_amount' => null,
        ]));

        $this->assertViolates('claims_denied_has_no_amount', fn () => Claim::factory()->create([
            'status' => ClaimStatus::Denied,
            'approved_amount' => '100.00',
        ]));

        $this->assertViolates('claims_estimated_amount_non_negative', fn () => Claim::factory()->create(['estimated_amount' => '-5.00']));
    }

    public function test_records_with_policies_cannot_be_hard_deleted(): void
    {
        $policy = Policy::factory()->create();

        $this->assertViolates('policies_client_id_foreign', fn () => $policy->client->forceDelete());
        $this->assertViolates('policies_insurer_id_foreign', fn () => $policy->insurer->forceDelete());
        $this->assertViolates('policies_insurance_product_id_foreign', fn () => $policy->product->forceDelete());
        $this->assertViolates('policies_broker_id_foreign', fn () => $policy->broker->forceDelete());
    }

    public function test_policy_with_claims_cannot_be_hard_deleted(): void
    {
        $claim = Claim::factory()->create();

        $this->assertViolates('claims_policy_id_foreign', fn () => $claim->policy->forceDelete());
    }

    public function test_hard_deleting_a_policy_cascades_to_coverages_and_installments(): void
    {
        $policy = Policy::factory()->withInstallments()->create(['installments_count' => 3]);
        Coverage::factory()->count(2)->create(['policy_id' => $policy->id]);

        $policy->forceDelete();

        $this->assertDatabaseMissing('coverages', ['policy_id' => $policy->id]);
        $this->assertDatabaseMissing('installments', ['policy_id' => $policy->id]);
    }

    public function test_claim_status_history_is_append_only_in_the_database(): void
    {
        $history = ClaimStatusHistory::factory()->create();

        $this->assertViolates('append-only', fn () => DB::table('claim_status_histories')
            ->where('id', $history->id)
            ->update(['notes' => 'tampered']));
    }

    public function test_activity_logs_are_append_only_in_the_database(): void
    {
        $policy = Policy::factory()->create();
        $log = $policy->activities()->create(['action' => 'created']);

        $this->assertViolates('append-only', fn () => DB::table('activity_logs')
            ->where('id', $log->id)
            ->update(['action' => 'updated']));

        $this->assertViolates('activity_logs_action_check', fn () => DB::table('activity_logs')->insert([
            'subject_type' => 'policy',
            'subject_id' => $policy->id,
            'action' => 'viewed',
        ]));
    }

    public function test_append_only_models_refuse_updates_and_deletes(): void
    {
        $history = ClaimStatusHistory::factory()->create();

        $this->assertThrows(fn () => $history->update(['notes' => 'tampered']), LogicException::class);
        $this->assertThrows(fn () => $history->delete(), LogicException::class);
        $this->assertNull($history->fresh()->notes);
    }

    private function assertViolates(string $expectedInMessage, callable $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (QueryException $exception) {
            $this->assertStringContainsString($expectedInMessage, $exception->getMessage());

            return;
        }

        $this->fail("Expected the database to reject the operation ({$expectedInMessage}).");
    }
}
