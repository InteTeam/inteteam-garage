# 016 — mechanics.locale

**Migration file:** `database/migrations/20260608000001_add_locale_to_mechanics_table.php`
**Added:** 2026-06-08
**Phase:** 6 — i18n & LLM Translation

## Purpose

Adds an explicit per-mechanic locale source for the translation pipeline. Resolves the long-standing Q1 (locale resolution) — `Mechanic.locale` is now the canonical source for `fromLocale` when previewing or auto-translating mechanic-authored content.

Previously, `EstimateController::previewTranslation()` hardcoded `fromLocale = 'en'`, which broke any preview where the mechanic actually wrote in a non-English locale.

## Schema change

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `locale` | `string(5)` | yes | `null` | ISO 639-1 (2 chars) or extended `xx-XX` (5 chars). `null` = inherit from `Garage.locale`. |

Added via `Schema::table('mechanics', ...)` after the `role` column.

## Resolution logic

```
mechanic.locale  ??  mechanic.garage.locale  ??  'en'
```

Helper: `Mechanic::resolvedLocale(): string` — single resolution point.

The nullable column + runtime fallback is the C-hybrid pattern from the Phase 5 tenancy decision: per-mechanic when set, garage-default otherwise, hardcoded floor of `'en'` as last resort. Same shape will be reused if a `Company.locale` parent layer is ever added.

## Why nullable instead of `default 'en'`

A column default would force every mechanic into `'en'` regardless of the garage they joined. Nullable lets the garage default win automatically — admins don't have to edit every mechanic when they create a Polish-speaking garage. Future-Proof: any later `Company.locale` slots between `mechanic` and `garage` in the resolver without altering existing data.

## Related decisions

- **Q1 (Phase 6) — RESOLVED 2026-06-08.** See memory `garage-phase6-deferred.md`.
- Auto-detect on input text (langid or OpenAI prompt) is the belt-and-braces fallback for misconfigured `Mechanic.locale` — implemented in a follow-up commit on `TranslationService`.
- Customer-side `toLocale` comes from CRM via `CrmApiService::getCustomer()` — handled in the controller-fix commit that supersedes the current hardcoded `'en'` source path.

## Related migrations

- `002_mechanics` — parent table
- `001_garages` — provides the fallback `Garage.locale` column
