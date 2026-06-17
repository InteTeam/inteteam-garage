<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RepairJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class JobService
{
    /**
     * Create a new repair job and assign at least one mechanic.
     *
     * planning.md L55 — "Job create form requires at least one mechanic
     * assigned before submission". Enforced by StoreJobRequest at validation
     * time; sync happens here inside a transaction so a partial pivot write
     * never leaves an orphaned job.
     *
     * @param  array{vehicle_id: string, mechanic_ids: list<string>}  $data
     */
    public function create(array $data): RepairJob
    {
        return DB::transaction(function () use ($data): RepairJob {
            $job = RepairJob::create(['vehicle_id' => $data['vehicle_id']]);
            $job->mechanics()->sync($data['mechanic_ids']);

            return $job;
        });
    }

    /**
     * Active (non-collected) jobs for the mechanic dashboard, newest first.
     * Eager-loads relations that the Dashboard.tsx Inertia page reads.
     *
     * @return Collection<int, RepairJob>
     */
    public function activeForDashboard(): Collection
    {
        return RepairJob::with(['vehicle', 'mechanics.user'])
            ->whereNotIn('state', [RepairJob::STATE_COLLECTED])
            ->latest()
            ->get();
    }

    /**
     * Full job list for the /jobs index page, newest first. Includes collected
     * jobs (the dashboard hides them; /jobs is a complete record).
     *
     * @return Collection<int, RepairJob>
     */
    public function listForIndex(): Collection
    {
        return RepairJob::with(['vehicle', 'mechanics'])
            ->latest()
            ->get();
    }
}
