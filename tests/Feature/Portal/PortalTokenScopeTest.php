<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\ApprovalEvent;
use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\RepairJob;
use App\Models\SignedPortalToken;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PortalTokenScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_token_resolves_its_bound_job(): void
    {
        [, $job] = $this->makeJob();
        $token = $this->makeToken($job);

        $this->get(route('portal.show', ['token' => $token->token]))
            ->assertOk();
    }

    public function test_expired_token_returns_404(): void
    {
        [, $job] = $this->makeJob();
        $token = $this->makeToken($job, expiresAt: now()->subMinute());

        $this->get(route('portal.show', ['token' => $token->token]))
            ->assertNotFound();
    }

    public function test_revoked_token_returns_404(): void
    {
        [, $job] = $this->makeJob();
        $token = $this->makeToken($job, revokedAt: now());

        $this->get(route('portal.show', ['token' => $token->token]))
            ->assertNotFound();
    }

    public function test_nonexistent_token_returns_404(): void
    {
        $this->get('/portal/this-token-does-not-exist')
            ->assertNotFound();
    }

    public function test_token_for_job_a_cannot_modify_line_item_belonging_to_job_b(): void
    {
        $garage = $this->makeGarage();
        [, $jobA] = $this->makeJob($garage);
        [, $jobB] = $this->makeJob($garage);

        $this->makeEstimateWithLineItem($jobA);
        $lineItemB = $this->makeEstimateWithLineItem($jobB);

        $tokenA = $this->makeToken($jobA);

        $this->post(route('portal.line-items.approve', [
            'token' => $tokenA->token,
            'lineItem' => $lineItemB->id,
        ]))->assertStatus(500);

        $this->assertDatabaseHas('line_items', [
            'id' => $lineItemB->id,
            'status' => LineItem::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $jobA->id,
            'event_type' => ApprovalEvent::EVENT_LINE_ITEM_APPROVED,
        ]);
        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $jobB->id,
            'event_type' => ApprovalEvent::EVENT_LINE_ITEM_APPROVED,
        ]);
    }

    public function test_token_for_garage_a_cannot_modify_line_item_in_garage_b(): void
    {
        $garageA = $this->makeGarage();
        $garageB = $this->makeGarage();
        [, $jobA] = $this->makeJob($garageA);
        [, $jobB] = $this->makeJob($garageB);

        $this->makeEstimateWithLineItem($jobA);
        $lineItemB = $this->makeEstimateWithLineItem($jobB);

        $tokenA = $this->makeToken($jobA);

        $this->post(route('portal.line-items.approve', [
            'token' => $tokenA->token,
            'lineItem' => $lineItemB->id,
        ]))->assertStatus(500);

        $this->assertDatabaseHas('line_items', [
            'id' => $lineItemB->id,
            'status' => LineItem::STATUS_PENDING,
        ]);
    }

    private function makeGarage(): Garage
    {
        return Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);
    }

    /**
     * @return array{0: Garage, 1: RepairJob}
     */
    private function makeJob(?Garage $garage = null): array
    {
        $garage ??= $this->makeGarage();

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
            'state' => RepairJob::STATE_AWAITING_APPROVAL,
        ]);

        return [$garage, $job];
    }

    private function makeToken(
        RepairJob $job,
        ?Carbon $expiresAt = null,
        ?Carbon $revokedAt = null,
    ): SignedPortalToken {
        return SignedPortalToken::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'token' => Str::ulid()->toString(),
            'expires_at' => $expiresAt ?? now()->addDays(30),
            'revoked_at' => $revokedAt,
        ]);
    }

    private function makeEstimateWithLineItem(RepairJob $job): LineItem
    {
        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'job_id' => $job->id,
            'revision_number' => 1,
            'sent_at' => now(),
        ]);

        return LineItem::withoutGlobalScopes()->create([
            'garage_id' => $job->garage_id,
            'estimate_id' => $estimate->id,
            'description' => 'Test work',
            'price' => 100.00,
            'status' => LineItem::STATUS_PENDING,
        ]);
    }
}
