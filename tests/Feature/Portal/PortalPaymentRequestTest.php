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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class PortalPaymentRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_amount_for_approved_line_items_only(): void
    {
        [, $job, $token] = $this->scenarioWithLineItems(paymentEnabled: true, statuses: [
            LineItem::STATUS_APPROVED,
            LineItem::STATUS_DECLINED,
            LineItem::STATUS_PENDING,
        ]);

        $this->get(route('portal.payment.show', ['token' => $token->token]))
            ->assertOk();
    }

    public function test_request_succeeds_when_payment_enabled(): void
    {
        Http::fake([
            '*/api/v1/internal/payments' => Http::response(['reference' => 'PAY-REF-123'], 200),
        ]);

        [, $job, $token] = $this->scenarioWithLineItems(paymentEnabled: true, statuses: [
            LineItem::STATUS_APPROVED,
        ]);

        $this->post(route('portal.payment.request', ['token' => $token->token]))
            ->assertRedirect();

        $this->assertSame('PAY-REF-123', $job->fresh()->payment_reference);
        $this->assertDatabaseHas('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_PAYMENT_REQUESTED,
            'actor_type' => ApprovalEvent::ACTOR_SYSTEM,
        ]);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v1/internal/payments'));
    }

    public function test_request_aborts_when_payment_disabled_on_garage(): void
    {
        [, $job, $token] = $this->scenarioWithLineItems(paymentEnabled: false, statuses: [
            LineItem::STATUS_APPROVED,
        ]);

        $this->post(route('portal.payment.request', ['token' => $token->token]))
            ->assertStatus(422);

        $this->assertNull($job->fresh()->payment_reference);
        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_PAYMENT_REQUESTED,
        ]);
    }

    public function test_request_rejected_when_payment_already_confirmed(): void
    {
        [, $job, $token] = $this->scenarioWithLineItems(paymentEnabled: true, statuses: [
            LineItem::STATUS_APPROVED,
        ]);
        $job->update(['payment_confirmed_at' => now()]);

        $this->post(route('portal.payment.request', ['token' => $token->token]))
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseMissing('approval_events', [
            'job_id' => $job->id,
            'event_type' => ApprovalEvent::EVENT_PAYMENT_REQUESTED,
        ]);
    }

    public function test_request_with_expired_token_returns_404(): void
    {
        [, $job, $token] = $this->scenarioWithLineItems(paymentEnabled: true, statuses: [
            LineItem::STATUS_APPROVED,
        ]);
        $token->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->post(route('portal.payment.request', ['token' => $token->token]))
            ->assertNotFound();
    }

    /**
     * @param  list<string>  $statuses
     * @return array{0: Garage, 1: RepairJob, 2: SignedPortalToken}
     */
    private function scenarioWithLineItems(bool $paymentEnabled, array $statuses): array
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => $paymentEnabled,
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
            'state' => RepairJob::STATE_APPROVED,
        ]);

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
            'sent_at' => now(),
        ]);

        foreach ($statuses as $i => $status) {
            LineItem::withoutGlobalScopes()->create([
                'garage_id' => $garage->id,
                'estimate_id' => $estimate->id,
                'description' => 'Item ' . ($i + 1),
                'price' => 100.00 * ($i + 1),
                'status' => $status,
            ]);
        }

        $token = SignedPortalToken::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'token' => Str::ulid()->toString(),
            'expires_at' => now()->addDays(30),
            'revoked_at' => null,
        ]);

        return [$garage, $job, $token];
    }
}
