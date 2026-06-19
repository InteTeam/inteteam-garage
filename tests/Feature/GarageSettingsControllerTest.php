<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GarageSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_enable_compliance_reminders_with_valid_payload(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('settings.update'), [
                'name' => $garage->name,
                'default_notification_channel' => 'email',
                'online_payment_enabled' => false,
                'locale' => 'en',
                'compliance_reminders_enabled' => true,
                'compliance_reminders_channel' => 'sms',
                'compliance_reminders_windows' => [30, 7],
                'compliance_reminders_recipient' => 'customer',
                'compliance_reminders_types' => ['mot', 'tax'],
            ])
            ->assertRedirect();

        $garage->refresh();

        $this->assertTrue($garage->compliance_reminders_enabled);
        $this->assertSame('sms', $garage->compliance_reminders_channel);
        $this->assertSame([30, 7], $garage->compliance_reminders_windows);
        $this->assertSame('customer', $garage->compliance_reminders_recipient);
        $this->assertSame(['mot', 'tax'], $garage->compliance_reminders_types);
    }

    public function test_enabling_reminders_without_windows_is_rejected(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('settings.update'), [
                'name' => $garage->name,
                'default_notification_channel' => 'email',
                'online_payment_enabled' => false,
                'locale' => 'en',
                'compliance_reminders_enabled' => true,
                // no windows
                'compliance_reminders_types' => ['mot'],
            ])
            ->assertSessionHasErrors('compliance_reminders_windows');

        $garage->refresh();
        $this->assertFalse($garage->compliance_reminders_enabled);
    }

    public function test_enabling_reminders_without_types_is_rejected(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('settings.update'), [
                'name' => $garage->name,
                'default_notification_channel' => 'email',
                'online_payment_enabled' => false,
                'locale' => 'en',
                'compliance_reminders_enabled' => true,
                'compliance_reminders_windows' => [30],
                // no types
            ])
            ->assertSessionHasErrors('compliance_reminders_types');
    }

    public function test_invalid_window_value_is_rejected(): void
    {
        [$garage, $admin] = $this->makeGarageWithAdmin();

        $this->actingAs($admin)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('settings.update'), [
                'name' => $garage->name,
                'default_notification_channel' => 'email',
                'online_payment_enabled' => false,
                'locale' => 'en',
                'compliance_reminders_enabled' => true,
                'compliance_reminders_windows' => [9999],
                'compliance_reminders_types' => ['mot'],
            ])
            ->assertSessionHasErrors('compliance_reminders_windows.0');
    }

    public function test_non_admin_mechanic_cannot_update_settings(): void
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);
        $user = User::factory()->create();
        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_MECHANIC,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('settings.update'), [
                'name' => $garage->name,
                'default_notification_channel' => 'email',
                'online_payment_enabled' => false,
                'locale' => 'en',
                'compliance_reminders_enabled' => true,
                'compliance_reminders_windows' => [30],
                'compliance_reminders_types' => ['mot'],
                'compliance_reminders_recipient' => 'customer',
            ])
            ->assertForbidden();

        $garage->refresh();
        $this->assertFalse($garage->compliance_reminders_enabled);
    }

    /** @return array{0: Garage, 1: User} */
    private function makeGarageWithAdmin(): array
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);
        $user = User::factory()->create();
        Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => Mechanic::ROLE_GARAGE_ADMIN,
            'is_active' => true,
        ]);

        return [$garage, $user];
    }
}
