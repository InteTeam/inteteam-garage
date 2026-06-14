<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RepairJob;
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
}
