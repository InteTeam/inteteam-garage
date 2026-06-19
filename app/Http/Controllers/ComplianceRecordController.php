<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ComplianceType;
use App\Http\Requests\Vehicle\StoreComplianceRecordRequest;
use App\Models\ComplianceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Dvla\DvlaException;
use App\Services\Dvla\VehicleEnquiryService;
use App\Services\VehicleComplianceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class ComplianceRecordController extends Controller
{
    public function __construct(
        private readonly VehicleComplianceService $complianceService,
        private readonly VehicleEnquiryService $dvlaService,
    ) {}

    public function store(StoreComplianceRecordRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('createForVehicle', [ComplianceRecord::class, $vehicle]);

        /** @var User $user */
        $user = Auth::user();

        $this->complianceService->record(
            vehicle: $vehicle,
            type: ComplianceType::from($request->validated('type')),
            expiresOn: Carbon::parse($request->validated('expires_on')),
            note: $request->validated('note'),
            user: $user,
        );

        return redirect()->route('vehicles.show', $vehicle->id)
            ->with(['alert' => 'The compliance record was saved.', 'type' => 'success']);
    }

    public function refresh(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('createForVehicle', [ComplianceRecord::class, $vehicle]);

        if (! $this->dvlaService->isConfigured()) {
            // Env-var hint stays in the logs — flash gets the canonical user-facing form.
            Log::warning('DVLA refresh attempted while DVLA_VES_API_KEY is not configured.');

            return redirect()->route('vehicles.show', $vehicle->id)
                ->with(['alert' => 'The DVLA integration is not configured.', 'type' => 'error']);
        }

        /** @var User $user */
        $user = Auth::user();

        try {
            $result = $this->dvlaService->fetch($vehicle->registration);
            $created = $this->complianceService->applyDvlaResult($vehicle, $result, $user);
        } catch (DvlaException $e) {
            Log::warning('DVLA refresh failed', [
                'vehicle_id' => $vehicle->id,
                'registration' => $vehicle->registration,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('vehicles.show', $vehicle->id)
                ->with(['alert' => $this->dvlaErrorMessage($e), 'type' => 'error']);
        }

        $msg = $created === []
            ? 'The DVLA refresh returned no new expiry dates.'
            : 'The vehicle was refreshed from DVLA (' . count($created) . ' record(s) added).';

        return redirect()->route('vehicles.show', $vehicle->id)
            ->with(['alert' => $msg, 'type' => 'success']);
    }

    /**
     * Map a DvlaException factory to a canonical, user-facing flash message.
     * The raw exception text leaks API URLs and status codes — keep that in logs.
     */
    private function dvlaErrorMessage(DvlaException $e): string
    {
        return match ($e->kind()) {
            DvlaException::KIND_NOT_FOUND => 'The vehicle was not found in DVLA.',
            DvlaException::KIND_BAD_REQUEST => 'The registration was rejected by DVLA as invalid.',
            default => 'The DVLA service is currently unavailable.',
        };
    }
}
