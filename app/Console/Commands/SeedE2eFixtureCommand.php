<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Estimate;
use App\Models\Garage;
use App\Models\LineItem;
use App\Models\Mechanic;
use App\Models\RepairJob;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\JobStateMachine;
use App\Services\SignedPortalTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Seeds a deterministic fixture for the Playwright suite and writes the ids and
 * portal tokens Playwright needs to `storage/app/e2e-fixture.json`.
 *
 * Why a file rather than an HTTP endpoint: the whole mechanic dashboard is behind
 * Inte.Team SSO, and the alternative — a test-only login route — is an auth
 * bypass living in the same codebase as production. The specs instead drive the
 * surfaces that need no session (public landing, auth gating, and the
 * signed-token customer portal, which is a genuinely token-authenticated journey)
 * and read their fixture from a file on the shared bind mount. The `npm`
 * container cannot run artisan, so this is a documented prerequisite step rather
 * than a Playwright global setup.
 *
 * Refuses to run in production: it writes data and mints portal tokens.
 */
final class SeedE2eFixtureCommand extends Command
{
    protected $signature = 'garage:seed-e2e {--fresh : Delete a previous fixture run first}';

    protected $description = 'Seed deterministic Playwright E2E fixture data and write storage/app/e2e-fixture.json';

    public const FIXTURE_PATH = 'e2e-fixture.json';

    private const GARAGE_SLUG = 'e2e-garage';

    private const SECOND_GARAGE_SLUG = 'e2e-garage-other';

    public function handle(
        SignedPortalTokenService $tokens,
        JobStateMachine $stateMachine,
    ): int {
        if (app()->environment('production')) {
            $this->error('Refusing to seed E2E fixture data in production.');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->purge();
        }

        $fixture = DB::transaction(function () use ($tokens, $stateMachine): array {
            // Job awaiting the customer's approval — the portal's primary journey.
            [$garage, $job] = $this->makeGarageWithJob(self::GARAGE_SLUG, 'E2E Garage', 'E211 EEE');
            $stateMachine->transition($job, RepairJob::STATE_BOOKED);
            $stateMachine->transition($job, RepairJob::STATE_IN_PROGRESS);
            $stateMachine->transition($job, RepairJob::STATE_AWAITING_APPROVAL);

            $estimate = $job->fresh()->currentEstimate;

            // A second garage + job, so cross-token isolation is testable.
            [, $otherJob] = $this->makeGarageWithJob(self::SECOND_GARAGE_SLUG, 'E2E Other Garage', 'E222 OTH');

            return [
                'appUrl' => (string) config('app.url'),
                'garage' => ['id' => $garage->id, 'slug' => $garage->slug, 'name' => $garage->name],
                'job' => [
                    'id' => $job->id,
                    'state' => $job->fresh()->state,
                    'registration' => $job->vehicle->registration,
                    'token' => $tokens->createForJob($job)->token,
                    'estimateId' => $estimate->id,
                    'lineItemIds' => $estimate->lineItems()->pluck('id')->all(),
                ],
                'otherJob' => [
                    'id' => $otherJob->id,
                    'token' => $tokens->createForJob($otherJob)->token,
                    'lineItemIds' => $otherJob->currentEstimate->lineItems()->pluck('id')->all(),
                ],
            ];
        });

        File::ensureDirectoryExists(storage_path('app'));
        File::put(
            storage_path('app/' . self::FIXTURE_PATH),
            json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL,
        );

        $this->info('Fixture written to storage/app/' . self::FIXTURE_PATH);
        $this->line('Portal URL: /portal/' . $fixture['job']['token']);

        return self::SUCCESS;
    }

    /**
     * @return array{0: Garage, 1: RepairJob}
     */
    private function makeGarageWithJob(string $slug, string $name, string $registration): array
    {
        $garage = Garage::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'locale' => 'en',
                'default_notification_channel' => Garage::CHANNEL_EMAIL,
                'online_payment_enabled' => true,
            ],
        );

        $user = User::firstOrCreate(
            ['email' => $slug . '@e2e.test'],
            ['name' => $name . ' Admin', 'password' => ''],
        );

        Mechanic::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $user->id, 'garage_id' => $garage->id],
            ['role' => Mechanic::ROLE_GARAGE_ADMIN, 'is_active' => true],
        );

        $vehicle = Vehicle::withoutGlobalScopes()->firstOrCreate(
            ['garage_id' => $garage->id, 'registration' => $registration],
            [
                'crm_customer_id' => 'e2e-crm-customer',
                'make' => 'Ford',
                'model' => 'Focus',
                'year' => 2019,
                'colour' => 'Blue',
            ],
        );

        $job = RepairJob::withoutGlobalScopes()->forceCreate([
            'garage_id' => $garage->id,
            'vehicle_id' => $vehicle->id,
            'state' => RepairJob::STATE_CREATED,
        ]);

        $job->mechanics()->sync(
            Mechanic::withoutGlobalScopes()->where('garage_id', $garage->id)->pluck('id')->all()
        );

        $estimate = Estimate::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'job_id' => $job->id,
            'revision_number' => 1,
            'sent_at' => now(),
        ]);

        foreach ([['Front brake pads', 189.99], ['Brake fluid change', 49.50]] as [$description, $price]) {
            LineItem::withoutGlobalScopes()->create([
                'garage_id' => $garage->id,
                'estimate_id' => $estimate->id,
                'description' => $description,
                'price' => $price,
                'status' => LineItem::STATUS_PENDING,
            ]);
        }

        return [$garage, $job->fresh()];
    }

    private function purge(): void
    {
        $garageIds = Garage::withoutGlobalScopes()
            ->whereIn('slug', [self::GARAGE_SLUG, self::SECOND_GARAGE_SLUG])
            ->pluck('id');

        if ($garageIds->isEmpty()) {
            return;
        }

        // Children first — FKs are RESTRICT/CASCADE mixed across the audit tables.
        foreach (['approval_events', 'job_state_transitions', 'signed_portal_tokens', 'notification_preferences'] as $table) {
            DB::table($table)->whereIn('garage_id', $garageIds)->delete();
        }

        $jobIds = RepairJob::withoutGlobalScopes()->whereIn('garage_id', $garageIds)->pluck('id');
        // Pivot column is `repair_job_id`, not `job_id` — the FK naming differs
        // from the one used on the child tables above.
        DB::table('repair_job_mechanic')->whereIn('repair_job_id', $jobIds)->delete();

        $estimateIds = Estimate::withoutGlobalScopes()->whereIn('garage_id', $garageIds)->pluck('id');
        DB::table('line_items')->whereIn('estimate_id', $estimateIds)->delete();
        DB::table('estimates')->whereIn('garage_id', $garageIds)->delete();
        DB::table('job_stages')->whereIn('garage_id', $garageIds)->delete();
        DB::table('repair_jobs')->whereIn('garage_id', $garageIds)->delete();

        $this->line('Purged ' . $garageIds->count() . ' previous E2E garage(s) job data.');
    }
}
