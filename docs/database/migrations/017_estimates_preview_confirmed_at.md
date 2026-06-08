# 017 — estimates.preview_confirmed_at

**Migration file:** `database/migrations/20260608000002_add_preview_confirmed_at_to_estimates_table.php`
**Added:** 2026-06-08
**Phase:** 6 — i18n & LLM Translation

## Purpose

Enforces Q4 (preview-confirm gate) as a Poka-Yoke contract: a cross-locale estimate cannot be sent to the customer until the mechanic has explicitly confirmed the translation preview. Without this column the README acceptance claim *"broken English to customer not possible — translation enforced by locale pair"* would have been advisory documentation, not enforced code.

## Schema change

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `preview_confirmed_at` | `timestamp` | yes | `null` | Set by `EstimateService::confirmTranslation()` when mechanic confirms the side-by-side preview. `null` for same-locale estimates and any estimate that hasn't reached the confirm step. |

Added via `Schema::table('estimates', ...)` after the `sent_at` column.

## Gate behaviour

`EstimateService::guardSendable(Estimate $estimate, string $fromLocale, string $toLocale): void` throws `RuntimeException` when:

- `$fromLocale !== $toLocale`
- AND `$estimate->preview_confirmed_at === null`

Called by `EstimateController::send()` before any state transition. Failure becomes a session error (`'estimate'` key) rather than a 500 — UI shows actionable message ("you must confirm the preview first").

Same-locale estimates skip the gate entirely.

## Related decisions

- **Q4 (Phase 6) — RESOLVED 2026-06-08.** See memory `garage-phase6-deferred.md`.
- Companion migration `018_line_items_translation_audit` adds per-line-item audit columns (Q5 — edit + LLM-raw audit trail).

## Related migrations

- `005_estimates` — parent table
- `018_line_items_translation_audit` — companion Q5 audit columns
