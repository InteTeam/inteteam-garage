# 010 — media

**Migration file:** `database/migrations/20260520200455_create_media_table.php`
**Added:** 2026-05-20
**Phase:** 2 — Media & GCS

## Purpose

Tracks every file uploaded against a job stage. A `Media` row points at a GCS object via `gcs_path` and is the system-of-record for "what file lives where for which job stage." Files themselves live in Google Cloud Storage (`config('filesystems.disks.gcs')`); this table holds the metadata + audit fields. Once a `JobStage` is locked, `GcsService::upload()` rejects new uploads against it, so `media` only ever grows during a stage's active window.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `job_id` | ULID | no | — | FK → `repair_jobs.id` cascadeOnDelete |
| `job_stage_id` | ULID | no | — | FK → `job_stages.id` cascadeOnDelete |
| `gcs_path` | string | no | — | `{job_id}/{stage_slug}/{unix_ts}_{original_filename}` |
| `mime_type` | string | no | — | Validated against `jpg,jpeg,png,webp,mp4,mov` at request layer |
| `original_filename` | string | no | — | Preserved for display + downloads |
| `uploaded_by` | string | no | — | `users.id` of the mechanic (string for forward-compat with non-ULID IDs) |
| `uploaded_at` | dateTime | no | — | Server-side wall clock; cast to `datetime` |
| `created_at`, `updated_at` | timestamp | yes | — | |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `media_garage_id_job_id_index` | `(garage_id, job_id)` | List a job's media within a garage |
| `media_garage_id_job_stage_id_index` | `(garage_id, job_stage_id)` | List a stage's media within a garage |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `job_id` → `repair_jobs.id` (ULID, `cascadeOnDelete`) — explicit `constrained('repair_jobs')`
- `job_stage_id` → `job_stages.id` (ULID, `cascadeOnDelete`)

## Relationships

```
Garage ──hasMany──► Media
RepairJob ──hasMany──► Media (via job_id)
JobStage ──hasMany──► Media
```

`Media::repairJob()` declares `'job_id'` as the foreign key (not `repair_job_id`) to match the column.

## Model Configuration

- Class: `App\Models\Media` (final)
- Traits: `HasUlids`, `HasGarageScope`, `HasFactory`
- Policy: **none** (no `#[UsePolicy]` attribute — authorization flows through the parent `RepairJob` via `RepairJobPolicy::update`, enforced in `MediaController::store`)
- `$fillable`: `garage_id, job_id, job_stage_id, gcs_path, mime_type, original_filename, uploaded_by, uploaded_at`
- `casts()`: `uploaded_at => datetime`
- No `SoftDeletes` — uploads are append-only; correct way to "remove" a misuploaded asset is admin-driven hard delete + audit event (not yet implemented).

## Constraints & Invariants

- `gcs_path` is generated server-side from `{job_id}/{stage_slug}/{unix_ts}_{original_filename}` (`GcsService::upload`). Clients never supply it.
- Uploads against a locked stage are rejected at the service layer (`GcsService::upload` throws `RuntimeException`) and at the HTTP layer (`MediaController::store` returns 422). Tested by `MediaUploadTest::test_upload_rejected_for_locked_stage` and `StageLockTest`.
- Signed URLs are generated on demand via `GcsService::signedUrl()`; expiry minutes come from `services.gcs.signed_url_expiry_minutes` (default 30).
- No DB-level uniqueness on `gcs_path` — collision-resistant by construction (`unix_ts` prefix); harmless if duplicates ever land.

## Deviations from playbook

- **No `idx_garage_created`.** Only `(garage_id, job_id)` and `(garage_id, job_stage_id)` are present. Acceptable — most queries fan out from a job or stage.
- **No policy attached.** Other tenant models have `#[UsePolicy(...)]`; `Media` relies on parent `RepairJob` authorization. Document this on `RepairJobPolicy` if it ever drifts.

## Related Migrations

- `001_garages`, `004_repair_jobs`, `006_job_stages` — parents
