<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Garage;
use App\Models\RepairJob;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class InternalJobApiTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'garage-api-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.garage.internal_secret', self::SECRET);
    }

    public function test_valid_secret_returns_job(): void
    {
        $job = $this->makeJob();

        $this->withHeader('X-Garage-Secret', self::SECRET)
            ->getJson("/api/v1/jobs/{$job->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $job->id);
    }

    public function test_valid_secret_returns_media_payload(): void
    {
        $job = $this->makeJob();

        $this->withHeader('X-Garage-Secret', self::SECRET)
            ->getJson("/api/v1/jobs/{$job->id}/media")
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_missing_secret_header_rejects_with_401(): void
    {
        $job = $this->makeJob();

        $this->getJson("/api/v1/jobs/{$job->id}")->assertStatus(401);
    }

    public function test_wrong_secret_header_rejects_with_401(): void
    {
        $job = $this->makeJob();

        $this->withHeader('X-Garage-Secret', 'wrong')
            ->getJson("/api/v1/jobs/{$job->id}")
            ->assertStatus(401);
    }

    /**
     * The regression that matters: with the secret unset, `header() !== config()`
     * was `null !== null` — false — so a header-less request authenticated and the
     * whole internal API was open to the internet on any deploy that forgot the
     * env var. It must fail closed instead.
     */
    public function test_unconfigured_secret_fails_closed_and_does_not_authenticate(): void
    {
        Config::set('services.garage.internal_secret', null);

        $job = $this->makeJob();

        $this->getJson("/api/v1/jobs/{$job->id}")->assertStatus(500);
    }

    public function test_unconfigured_secret_rejects_even_with_a_header(): void
    {
        Config::set('services.garage.internal_secret', '');

        $job = $this->makeJob();

        $this->withHeader('X-Garage-Secret', '')
            ->getJson("/api/v1/jobs/{$job->id}")
            ->assertStatus(500);
    }

    private function makeJob(): RepairJob
    {
        $garage = Garage::create([
            'name' => 'API Garage',
            'slug' => 'api-garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => Garage::CHANNEL_EMAIL,
            'locale' => 'en',
        ]);

        $vehicle = Vehicle::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'crm_customer_id' => 'crm-api-1',
            'registration' => 'AP11 KEY',
            'make' => 'Ford',
            'model' => 'Focus',
        ]);

        return RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_CREATED,
        ]);
    }
}
