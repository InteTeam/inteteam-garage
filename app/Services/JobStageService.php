<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JobStage;
use App\Models\Mechanic;
use Illuminate\Database\Eloquent\Collection;

final class JobStageService
{
    public function __construct(
        private readonly TranslationService $translation,
        private readonly CrmApiService $crm,
    ) {}

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

    public function updateNotes(JobStage $stage, string $notes, Mechanic $author): JobStage
    {
        if (trim($notes) === '') {
            $stage->update([
                'notes' => $notes,
                'notes_translated' => null,
                'notes_source_locale' => null,
                'notes_target_locale' => null,
                'notes_translated_at' => null,
            ]);

            return $stage->fresh();
        }

        $stage->load('repairJob.vehicle', 'garage');

        $fromLocale = $author->resolvedLocale();
        $crmCustomerId = (string) ($stage->repairJob->vehicle->crm_customer_id ?? '');
        $toLocale = ($crmCustomerId !== ''
            ? $this->crm->getCustomerLocale($crmCustomerId)
            : null) ?? $stage->garage->locale;

        $verifiedFrom = $this->translation->verifySourceLocale($fromLocale, $notes);
        $needsTranslation = $verifiedFrom !== $toLocale;

        $stage->update([
            'notes' => $notes,
            'notes_translated' => $needsTranslation
                ? $this->translation->translate($notes, $verifiedFrom, $toLocale, 'stage_notes')
                : null,
            'notes_source_locale' => $verifiedFrom,
            'notes_target_locale' => $needsTranslation ? $toLocale : null,
            'notes_translated_at' => $needsTranslation ? now() : null,
        ]);

        return $stage->fresh();
    }
}
