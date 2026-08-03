<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Binds a Garage to its inteteam_crm company.
 *
 * The CRM is multi-tenant per `companies.id` and every one of its
 * `api/v1/internal/*` endpoints is company-scoped (`InternalController::byEmail`
 * returns 400 without `company_id`). This app had no such column, so no CRM call
 * could ever be tenant-correct — the integration could not work even with the
 * right auth header.
 *
 * Nullable on purpose: a garage may exist before its CRM company is provisioned,
 * and `CrmApiService` falls back to the app-wide `CRM_COMPANY_ID` when the column
 * is null. `crm_company_slug` is stored alongside because the customer SSO
 * exchange route takes a **slug** path segment
 * (`/api/v1/auth/customer/{company}/sso/exchange`) while the internal endpoints
 * take the **id** — the CRM exposes no id↔slug lookup, so both are persisted.
 *
 * No FK: the referenced row lives in a different database owned by another
 * service. Documented in `docs/database/migrations/031_garages_crm_company.md`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garages', function (Blueprint $table): void {
            // CRM company ids are ULIDs (char 26); sized to 36 to tolerate a
            // future uuid without another migration.
            $table->string('crm_company_id', 36)->nullable()->after('slug');
            $table->string('crm_company_slug', 255)->nullable()->after('crm_company_id');

            $table->index('crm_company_id', 'idx_garages_crm_company');
        });
    }

    public function down(): void
    {
        Schema::table('garages', function (Blueprint $table): void {
            $table->dropIndex('idx_garages_crm_company');
            $table->dropColumn(['crm_company_id', 'crm_company_slug']);
        });
    }
};
