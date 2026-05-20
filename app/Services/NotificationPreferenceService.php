<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Collection;

final class NotificationPreferenceService
{
    public function getAll(): Collection
    {
        return NotificationPreference::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): NotificationPreference
    {
        return NotificationPreference::create($data);
    }

    public function update(NotificationPreference $notificationPreference, array $data): NotificationPreference
    {
        $notificationPreference->update($data);

        return $notificationPreference->fresh();
    }

    public function delete(NotificationPreference $notificationPreference): void
    {
        $notificationPreference->delete();
    }
}
