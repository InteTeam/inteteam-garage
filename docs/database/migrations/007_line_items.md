# 007 — line_items

**Migration file:** `database/migrations/20260520200435_create_line_items_table.php`
**Altered by:** `database/migrations/20260521000001_add_customer_notes_to_line_items_table.php` (adds `customer_notes`)
**Added:** 2026-05-20 (column added 2026-05-21)
**Phase:** 1 — Multi-Tenancy & Core Models (approval flow logic is Phase 3)

## Purpose

A single approvable item on an estimate: a description, a price, and a per-item customer decision (`pending` → `approved` | `declined`). Approvals/declines happen via the customer portal and are audited in `ApprovalEvent` (see `012_approval_events`). The customer can also attach a free-text question (`customer_notes`) when querying an item.

## Schema

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | ULID | no | — | PK |
| `garage_id` | ULID | no | — | FK → `garages.id` cascadeOnDelete |
| `estimate_id` | ULID | no | — | FK → `estimates.id` cascadeOnDelete |
| `description` | string | no | — | Free-text work description |
| `price` | decimal(10,2) | no | — | Cast as `decimal:2` (string in PHP, preserves precision) |
| `status` | string | no | `LineItem::STATUS_PENDING` (`pending`) | One of `LineItem::STATUSES` |
| `customer_notes` | text | yes | — | Customer-side note/question; added in 016 |
| `created_at`, `updated_at` | timestamp | yes | — | |
| `deleted_at` | timestamp | yes | — | `SoftDeletes` |

## Indexes

| Name | Columns | Purpose |
|---|---|---|
| `line_items_garage_id_estimate_id_index` | `(garage_id, estimate_id)` | Per-estimate item list |
| `line_items_garage_id_status_index` | `(garage_id, status)` | Per-tenant status filters (e.g. "all pending") |

## Foreign Keys

- `garage_id` → `garages.id` (ULID, `cascadeOnDelete`)
- `estimate_id` → `estimates.id` (ULID, `cascadeOnDelete`)

## Relationships

```
Estimate ──hasMany──► LineItem
```

## Model Configuration

- Class: `App\Models\LineItem` (final)
- Traits: `HasUlids`, `HasGarageScope`, `SoftDeletes`, `HasFactory`
- Policy: `#[UsePolicy(LineItemPolicy::class)]`
- `$fillable`: `garage_id, estimate_id, description, price, status, customer_notes`
- `casts()`: `price => decimal:2`
- Constants: `LineItem::STATUS_PENDING | STATUS_APPROVED | STATUS_DECLINED`, list in `LineItem::STATUSES`

## Constraints & Invariants

- Once a line item has `status != pending`, the parent estimate is considered "responded" (`Estimate::hasCustomerResponse()`) and becomes immutable — further changes require a new revision (see `005_estimates`).
- `price` is cast as `decimal:2` — PHP receives a string. Use `bcmath` or explicit casts for arithmetic; do not coerce via `(float)` before persisting.
- `status` writes go through `App\Services\ApprovalEventService` — any write to `status` should also append to `ApprovalEvent`. Direct `LineItem::update(['status' => …])` bypasses the audit log.
- Customer-side writes happen on portal routes (`routes/portal.php`) — all of those go through `PortalLineItemController` which uses `ApprovalEventService`.
- Payment amount calculation includes only `status = approved` items (`CrmPaymentService::calculateAmount()`).

## Deviations from playbook

- **Missing `idx_garage_created`.** Two indexes on `garage_id` are present but neither covers `created_at`. Add if line-item history list views grow.

## Related Migrations

- `001_garages`, `005_estimates` — parents
- `012_approval_events` — every status mutation should append here
- `016_add_customer_notes_to_line_items` — adds `customer_notes` column
