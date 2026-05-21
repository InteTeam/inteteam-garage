# Component Inventory — Garage Core

Audit of existing components from inteteam_crm that can be reused before building new ones.

## From inteteam_crm (via copy/adapt)

| Component | CRM location | Reuse plan |
|---|---|---|
| `AdminLayout` | `Components/layouts/AdminLayout.tsx` | Copy, rename to `GarageLayout`, remove barcode scanner, adjust nav |
| shadcn `Button` | `Components/ui/button.tsx` | Copy as-is |
| shadcn `Dialog` | `Components/ui/dialog.tsx` | Copy as-is |
| shadcn `Table` | `Components/ui/table.tsx` | Copy as-is |
| shadcn `Input` | `Components/ui/input.tsx` | Copy as-is |
| shadcn `Label` | `Components/ui/label.tsx` | Copy as-is |
| shadcn `Select` | `Components/ui/select.tsx` | Copy as-is |
| shadcn `Toast` / `Toaster` | `Components/ui/toast.tsx` | Copy as-is |
| shadcn `Badge` | `Components/ui/badge.tsx` | Copy as-is |

## New components needed (not in CRM)

| Component | Purpose |
|---|---|
| `JobStateBadge` | Coloured badge per job state |
| `StageTimeline` | Visual timeline of job stages |
| `MediaUploadZone` | Drag-drop + camera upload for GCS |
| `MediaGallery` | Grid of signed-URL images/videos |
| `LineItemRow` | Estimate line item with approve/decline actions |
| `HandoverChecklist` | Customer inspection form (checkbox + notes per item) |
| `PortalNavBar` | Minimal header for customer portal (no auth nav) |
| `ApprovalTimeline` | Customer read-only event timeline |
