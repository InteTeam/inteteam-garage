<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RepairJob;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        $activeJobs = RepairJob::with(['vehicle', 'mechanics'])
            ->whereNotIn('state', [RepairJob::STATE_COLLECTED])
            ->latest()
            ->get();

        return Inertia::render('Dashboard', [
            'activeJobs' => $activeJobs,
        ]);
    }
}
