<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-shot bootstrap for a garage_admin on a fresh environment. Called from
 * post-deploy.sh when GARAGE_BOOTSTRAP_ADMIN_EMAIL is set in .env. Idempotent
 * via firstOrCreate — safe to run on every deploy. Remove the env var once
 * the admin exists.
 */
final class BootstrapAdminCommand extends Command
{
    protected $signature = 'garage:bootstrap-admin
                            {--email= : Email of the SSO user to promote to garage_admin}
                            {--garage-slug=test-garage : Slug of the Garage to create/attach to}
                            {--garage-name=Test Garage : Display name for the Garage (used only on create)}';

    protected $description = 'Bootstrap a Garage + User + Mechanic(garage_admin) so an SSO user can log in on a fresh environment.';

    public function handle(): int
    {
        $email = (string) $this->option('email');

        if ($email === '') {
            $this->info('No --email provided — skipping bootstrap.');

            return self::SUCCESS;
        }

        $garageSlug = (string) $this->option('garage-slug');
        $garageName = (string) $this->option('garage-name');

        $garage = Garage::firstOrCreate(
            ['slug' => $garageSlug],
            ['name' => $garageName, 'locale' => 'en'],
        );

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Bootstrap Admin', 'password' => ''],
        );

        $mechanic = Mechanic::withoutGlobalScopes()->firstOrCreate(
            ['user_id' => $user->id, 'garage_id' => $garage->id],
            ['role' => Mechanic::ROLE_GARAGE_ADMIN, 'is_active' => true],
        );

        $this->info(sprintf(
            'garage_admin ready: user=%s garage=%s mechanic=%s role=%s',
            $user->email,
            $garage->slug,
            $mechanic->id,
            $mechanic->role,
        ));

        return self::SUCCESS;
    }
}
