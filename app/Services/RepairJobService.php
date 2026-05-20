<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RepairJob;
use Illuminate\Database\Eloquent\Collection;

final class RepairJobService
{
    public function getAll(): Collection
    {
        return RepairJob::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): RepairJob
    {
        return RepairJob::create($data);
    }

    public function update(RepairJob $repairJob, array $data): RepairJob
    {
        $repairJob->update($data);

        return $repairJob->fresh();
    }

    public function delete(RepairJob $repairJob): void
    {
        $repairJob->delete();
    }
}
