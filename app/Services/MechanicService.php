<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Mechanic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class MechanicService
{
    public function getAll(): Collection
    {
        return Mechanic::query()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Slim active-mechanic list for the job-create picker.
     *
     * @return \Illuminate\Support\Collection<int, array{id: string, name: string, role: string}>
     */
    public function listForJobPicker(): \Illuminate\Support\Collection
    {
        return Mechanic::query()
            ->with('user:id,name')
            ->where('is_active', true)
            ->get(['id', 'user_id', 'role'])
            ->map(fn (Mechanic $m) => [
                'id' => $m->id,
                'name' => $m->user->name,
                'role' => $m->role,
            ])
            ->values();
    }

    /**
     * Users that don't yet have a Mechanic record in the current garage.
     * A user with a Mechanic elsewhere still shows up here — one person
     * can staff multiple garages.
     *
     * @return Collection<int, User>
     */
    public function listUnassignedUsers(): Collection
    {
        $currentGarageId = session('current_garage_id');

        return User::query()
            ->whereDoesntHave('mechanic', fn ($q) => $q
                ->withoutGlobalScopes()
                ->where('garage_id', $currentGarageId))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    public function create(array $data): Mechanic
    {
        return Mechanic::create($data);
    }

    public function update(Mechanic $mechanic, array $data): Mechanic
    {
        $mechanic->update($data);

        return $mechanic->fresh();
    }

    public function delete(Mechanic $mechanic): void
    {
        $mechanic->delete();
    }
}
