<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JobStage;
use Illuminate\Database\Eloquent\Collection;

final class JobStageService
{
    public function getAll(): Collection
    {
        return JobStage::query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): JobStage
    {
        return JobStage::create($data);
    }

    public function update(JobStage $jobStage, array $data): JobStage
    {
        $jobStage->update($data);

        return $jobStage->fresh();
    }

    public function delete(JobStage $jobStage): void
    {
        $jobStage->delete();
    }
}
