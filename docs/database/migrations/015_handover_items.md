# 015 — handover_items

**Migration file:** `database/migrations/20260520200520_create_handover_items_table.php`
**Added:** 2026-05-20
**Phase:** 4 — Customer Handover & Online Payment

## Purpose

The per-line-item checklist the customer fills in at handover: did each repair line item meet expectations? Written atomically with the parent `HandoverInspection` row (`PortalHandoverController@submit` wraps both in a transaction). `notes` is required when `accepted = false` (mechanic dashboard surfaces these as flagged items so the garage can follow up).

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `handover_inspection_id` | ULID | no | — | FK → `handover_inspections.id` cascadeOnDelete |
| `line_item_id` | ULID | no | — | FK → `line_items.id` cascadeOnDelete |
| `accepted` | boolean | no | — | `true` = customer signed off, `false` = customer disputes the work |
| `notes` | text | yes | — | **Required when `accepted = false`** — enforced by `PortalHandoverController@submit`, not at DB |
| `created_at`, `updated_at` | timestamp | yes | — | |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `handover_items_handover_inspection_id_line_item_id_unique` | `(handover_inspection_id, line_item_id)` | One item per line item per inspection — prevents accidental double-rows |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `handover_inspection_id` → `handover_inspections.id` (ULID, `cascadeOnDelete`)
- `line_item_id` → `line_items.id` (ULID, `cascadeOnDelete`)

## Relationships

```
HandoverInspection ──hasMany──► HandoverItem ──belongsTo──► LineItem
```

## Model Configuration

- Class: `App\Models\HandoverItem` (final)
- Traits: `HasUlids`, `HasGarageScope`, `HasFactory`
- Policy: **none** — written through portal controller, scoped by token
- `$fillable`: `garage_id, handover_inspection_id, line_item_id, accepted, notes`
- `casts()`: `accepted => boolean`

## Constraints & Invariants

- `accepted = false` requires non-null `notes` — application-level rule in `PortalHandoverController@submit`. Migration does not enforce this with a CHECK constraint because MariaDB CHECK constraints have been historically flaky; consider adding when stable.
- `(handover_inspection_id, line_item_id)` unique — second item for the same line in the same inspection fails at DB layer.
- Cascade delete: removing a `HandoverInspection` removes its `HandoverItem` rows. Removing a `LineItem` removes any handover item that references it (which is rare — line items don't get deleted under normal flow).

## Deviations from playbook

- **No `idx_garage_created`.** Same rationale as `014_handover_inspections` — no tenant-time view of handover items in current product surface.
- **No DB-level CHECK** for the `accepted/notes` rule. Application-enforced only.

## Related Migrations

- `001_garages` — parent (tenant)
- `014_handover_inspections` — parent (inspection)
- `007_line_items` — referenced line items
