<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\JobService;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly JobService $jobs,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'activeJobs' => $this->jobs->activeForDashboard(),
        ]);
    }
}
