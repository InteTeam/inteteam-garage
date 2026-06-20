<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RepairJob\SyncJobMechanicsRequest;
use App\Models\RepairJob;
use Illuminate\Http\RedirectResponse;

final class JobMechanicController extends Controller
{
    public function sync(SyncJobMechanicsRequest $request, RepairJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        /** @var array{mechanic_ids: list<string>} $validated */
        $validated = $request->validated();

        $job->mechanics()->sync($validated['mechanic_ids']);

        return back()->with(['alert' => 'The mechanic assignments were updated.', 'type' => 'success']);
    }
}
