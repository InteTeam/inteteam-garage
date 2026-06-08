# 019 — job_stages notes + translation columns

**Migration file:** `database/migrations/20260608000004_add_notes_to_job_stages_table.php`
**Added:** 2026-06-08
**Phase:** 6 — i18n & LLM Translation

## Purpose

Implements Q2: stage notes are a new first-class field on `JobStage`, with eager auto-translation captured alongside the source. When a mechanic writes a stage note in their locale and the customer locale differs, `JobStageService::updateNotes()` calls the translation pipeline at save time and persists both copies. The customer portal reads `notes_translated` and shows a "Translated by AI" disclaimer when `notesWereTranslatedByAi()` is true.

This avoids the "lazy-translate on read" failure mode (mechanic doesn't see translation errors at write time; customer hits OpenAI latency on every portal page load).

## Schema change

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `notes` | `text` | yes | `null` | Source text as written by the mechanic. |
| `notes_translated` | `text` | yes | `null` | Translated version. `null` when same-locale or empty notes. |
| `notes_source_locale` | `string(5)` | yes | `null` | Verified source locale (post `verifySourceLocale` override). |
| `notes_target_locale` | `string(5)` | yes | `null` | Resolved customer locale (`null` if no translation was needed). |
| `notes_translated_at` | `timestamp` | yes | `null` | Set at translation time, used by portal disclaimer. |

All columns are nullable: existing stages have no notes; same-locale notes skip the translation columns.

## Eager-translation flow

`JobStageService::updateNotes(JobStage $stage, string $notes, Mechanic $author)`:

1. Resolve `fromLocale` via `Mechanic::resolvedLocale()` (falls back to garage).
2. Resolve `toLocale` via `CrmApiService::getCustomerLocale()` with garage fallback.
3. `verifySourceLocale` may override `fromLocale` if auto-detect disagrees.
4. If `fromLocale !== toLocale`: `translate(..., context: 'stage_notes')` and persist both copies + locale pair + timestamp.
5. If same-locale: persist `notes` only; leave translation columns `null`.

Failure of CRM lookup falls back to garage locale (Poka-Yoke — never lose the note, even if CRM is unreachable).

## Portal disclaimer

Helper `JobStage::notesWereTranslatedByAi()` returns `true` iff `notes_translated !== null` and source/target differ. Portal renders the badge based on this single boolean.

## Cache

Re-saving identical text within the 24h cache window reuses the cached translation (single MD5 key on text-hash + locale pair). The `notes_translated_at` timestamp lets staff see when the translation was last regenerated, even if the underlying text was unchanged.

## Why eager (not lazy)

- **Poka-Yoke:** translation failures surface to the mechanic at save time when they can fix them, not to the customer at read time when they can't.
- **Future-Proof:** eager + cache gives lazy semantics on re-render anyway; lazy makes first portal load depend on OpenAI uptime, which is a permanent perf footgun.

## Related decisions

- **Q2 (Phase 6) — RESOLVED 2026-06-08.** See memory `garage-phase6-deferred.md`.

## Related migrations

- `006_job_stages` — parent table

## Not yet wired

The controller path (`JobStageController::update` accepting a `notes` field, form request validation, frontend form input) is **not** included in this commit. The migration + service helper land the data layer; the Inertia form wiring is a separate UI commit. Until that ships, `notes` is set programmatically only.
