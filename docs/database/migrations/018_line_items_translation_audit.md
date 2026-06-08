# 018 — line_items translation audit columns

**Migration file:** `database/migrations/20260608000003_add_translation_audit_to_line_items_table.php`
**Added:** 2026-06-08
**Phase:** 6 — i18n & LLM Translation

## Purpose

Implements Q5 audit trail: when a mechanic confirms the side-by-side translation preview, the LLM-raw output and the mechanic-shipped version are stored side-by-side per line item. If the mechanic edited the LLM output before confirming, the editor's mechanic ID is recorded.

This lets future compliance investigations answer: *"what did the AI say, what did the mechanic ship, who edited it?"*

## Schema change

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `translation_confirmed_text` | `text` | yes | `null` | The translated text the customer will see — either the LLM raw output or the mechanic-edited version. Source of truth for the customer-facing copy. |
| `translation_llm_raw` | `text` | yes | `null` | Original LLM output captured at confirm time. Diff against `translation_confirmed_text` reveals mechanic edits. |
| `translation_edited_by_mechanic_id` | `ULID` FK → `mechanics.id` | yes | `null` | Set when `translation_confirmed_text !== translation_llm_raw`. `nullOnDelete` so audit row survives mechanic deletion. |

All three are nullable: same-locale line items skip the translation flow entirely and leave these as `null`.

## Confirm flow

`POST /jobs/{job}/estimates/{estimate}/confirm-translation` accepts a `confirmations[]` array with `{id, translated_text, llm_raw_text}` per line item. `EstimateService::confirmTranslation()`:

1. For each confirmation: trim-compare `translated_text` vs `llm_raw_text`; if differs, set `translation_edited_by_mechanic_id` to the acting mechanic.
2. Persist all three columns per line item.
3. Set `Estimate.preview_confirmed_at = now()` (companion migration 017).

The whole confirm step runs inside a DB transaction so the gate flips atomically with the per-line-item audit.

## Why these columns live on `line_items`

- Translation is per-line-item (different descriptions, different glossary hits).
- Edits happen per-line-item (mechanic might fine-tune one item, accept the others).
- The estimate-level `preview_confirmed_at` is the **gate**; line-item columns are the **payload**.

## Related decisions

- **Q5 (Phase 6) — RESOLVED 2026-06-08.** See memory `garage-phase6-deferred.md`.
- Companion migration `017_estimates_preview_confirmed_at` provides the send-gate column.

## Related migrations

- `007_line_items` — parent table
- `017_estimates_preview_confirmed_at` — companion Q4 send-gate column
- `002_mechanics` — referenced by `translation_edited_by_mechanic_id` FK
