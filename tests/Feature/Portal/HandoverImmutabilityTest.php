<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Estimate;
use App\Models\Garage;
use App\Models\HandoverInspection;
use App\Models\HandoverItem;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Models\SignedPortalToken;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HandoverImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_submission_is_rejected(): void
    {
        [$job, $lineItem, $token] = $this->scenario();

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => [
                ['line_item_id' => $lineItem->id, 'accepted' => true, 'notes' => null],
            ]],
        )->assertRedirect();

        $this->assertDatabaseCount('handover_inspections', 1);
        $firstInspection = HandoverInspection::query()->where('job_id', $job->id)->firstOrFail();

        $this->post(
            route('portal.handover.submit', ['token' => $token->token]),
            ['items' => [
                ['line_item_id' => $lineItem->id, 'accepted' => false, 'notes' => 'Changed my mind'],
            ]],
        )->assertSessionHasErrors('handover');

        $this->assertDatabaseCount('handover_inspections', 1);
        $this->assertDatabaseHas('handover_inspections', ['id' => $firstInspection->id]);
        $this->assertDatabaseMissing('handover_items', [
            'line_item_id' => $lineItem->id,
            'accepted' => false,
        ]);
    }

    public function test_handover_items_unique_per_inspection_and_line_item(): void
    {
        [$job, $lineItem, $token] = $this->scenario();

        $inspection = HandoverInspection::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'submitted_by_token' => $token->token,
            'submitted_at' => now(),
        ]);

        HandoverItem::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'handover_inspection_id' => $inspection->id,
            'line_item_id' => $lineItem->id,
            'accepted' => true,
            'notes' => null,
        ]);

        $this->expectException(QueryException::class);

        HandoverItem::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'handover_inspection_id' => $inspection->id,
            'line_item_id' => $lineItem->id,
            'accepted' => false,
            'notes' => 'duplicate',
        ]);
    }

    /**
     * @return array{0: RepairJob, 1: LineItem, 2: SignedPortalToken}
     */
    private function scenario(): array
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-' . uniqid(),
            'registration' => Str::upper(Str::random(7)),
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        $job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_AWAITING_COLLECTION,
        ]);

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
            'sent_at' => now(),
        ]);

        $lineItem = LineItem::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'estimate_id' => $estimate->id,
            'description' => 'Brake pads',
            'price' => 120.00,
            'status' => LineItem::STATUS_APPROVED,
        ]);

        $token = SignedPortalToken::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'token' => Str::ulid()->toString(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);

        return [$job, $lineItem, $token];
    }
}
