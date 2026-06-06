<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RepairJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class JobMechanicController extends Controller
{
    public function sync(Request $request, RepairJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'mechanic_ids' => ['present', 'array'],
            'mechanic_ids.*' => ['ulid'],
        ]);

        $job->mechanics()->sync($validated['mechanic_ids']);

        return back()->with(['alert' => 'The mechanic assignments were updated.', 'type' => 'success']);
    }
}
