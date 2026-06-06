<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\Mechanic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RoleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_mechanic_cannot_create_mechanic(): void
    {
        [$garage, $user] = $this->setupMechanic(Mechanic::ROLE_MECHANIC);
        $otherUser = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->post(route('mechanics.store'), [
                'garage_id' => $garage->id,
                'user_id' => $otherUser->id,
                'role' => Mechanic::ROLE_MECHANIC,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('mechanics', ['user_id' => $otherUser->id]);
    }

    public function test_non_admin_mechanic_cannot_delete_mechanic(): void
    {
        [$garage, $user, $mechanic] = $this->setupMechanic(Mechanic::ROLE_MECHANIC);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->delete(route('mechanics.destroy', $mechanic->id))
            ->assertForbidden();

        $this->assertDatabaseHas('mechanics', ['id' => $mechanic->id, 'deleted_at' => null]);
    }

    public function test_non_admin_mechanic_cannot_update_settings(): void
    {
        [$garage, $user] = $this->setupMechanic(Mechanic::ROLE_MECHANIC);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('settings.update'), [
                'name' => 'Hijacked Garage',
                'default_notification_channel' => 'email',
                'online_payment_enabled' => true,
                'locale' => 'en',
            ])
            ->assertForbidden();

        $this->assertSame($garage->name, $garage->fresh()->name);
        $this->assertFalse($garage->fresh()->online_payment_enabled);
    }

    public function test_admin_mechanic_can_update_settings(): void
    {
        [$garage, $user] = $this->setupMechanic(Mechanic::ROLE_GARAGE_ADMIN);

        $this->actingAs($user)
            ->withSession(['current_garage_id' => $garage->id])
            ->put(route('settings.update'), [
                'name' => 'Renamed Garage',
                'default_notification_channel' => 'sms',
                'online_payment_enabled' => true,
                'locale' => 'en',
            ])
            ->assertRedirect();

        $this->assertSame('Renamed Garage', $garage->fresh()->name);
        $this->assertTrue($garage->fresh()->online_payment_enabled);
    }

    /**
     * @return array{0: Garage, 1: User, 2: Mechanic}
     */
    private function setupMechanic(string $role): array
    {
        $garage = Garage::create([
            'name' => 'Garage ' . uniqid(),
            'slug' => 'garage-' . uniqid(),
            'online_payment_enabled' => false,
            'default_notification_channel' => 'email',
            'locale' => 'en',
        ]);

        $user = User::factory()->create();

        $mechanic = Mechanic::withoutGlobalScopes()->create([
            'garage_id' => $garage->id,
            'user_id' => $user->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return [$garage, $user, $mechanic];
    }
}
