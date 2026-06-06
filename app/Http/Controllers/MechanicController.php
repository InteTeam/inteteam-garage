<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Mechanic\StoreMechanicRequest;
use App\Http\Requests\Mechanic\UpdateMechanicRequest;
use App\Models\Mechanic;
use App\Services\MechanicService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class MechanicController extends Controller
{
    public function __construct(
        private readonly MechanicService $mechanicService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Mechanic::class);

        return Inertia::render('Mechanics/Index', [
            'mechanics' => $this->mechanicService->getAll(),
        ]);
    }

    public function store(StoreMechanicRequest $request): RedirectResponse
    {
        $this->authorize('create', Mechanic::class);

        $this->mechanicService->create($request->validated());

        return redirect()->route('mechanics.index')
            ->with(['alert' => 'The mechanic was created.', 'type' => 'success']);
    }

    public function show(Mechanic $mechanic): Response
    {
        $this->authorize('view', $mechanic);

        return Inertia::render('Mechanics/Show', [
            'mechanic' => $mechanic,
        ]);
    }

    public function update(UpdateMechanicRequest $request, Mechanic $mechanic): RedirectResponse
    {
        $this->authorize('update', $mechanic);

        $this->mechanicService->update($mechanic, $request->validated());

        return redirect()->route('mechanics.index')
            ->with(['alert' => 'The mechanic was updated.', 'type' => 'success']);
    }

    public function destroy(Mechanic $mechanic): RedirectResponse
    {
        $this->authorize('delete', $mechanic);

        $this->mechanicService->delete($mechanic);

        return redirect()->route('mechanics.index')
            ->with(['alert' => 'The mechanic was deleted.', 'type' => 'success']);
    }
}
