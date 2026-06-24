# Customer Account Portal — React Component Inventory

## New components

| File | Purpose |
|---|---|
| `resources/js/Layouts/CustomerLayout.tsx` | Shell for `/account/*` pages. Sidebar nav, header, logout form. Sibling to `GarageLayout` (mechanic) and `PortalLayout` (signed-token). |
| `resources/js/Pages/Account/Dashboard.tsx` | Vehicles list + compliance traffic-light + recent jobs table. |
| `resources/js/Pages/Account/VehicleShow.tsx` | Vehicle details + read-only compliance tab. |
| `resources/js/Pages/Account/JobShow.tsx` | Read-only timeline, line items with approve/decline/question actions, stages with media thumbnails. |
| `resources/js/Pages/Account/Transactions.tsx` | Payment history from CRM. |
| `resources/js/Pages/Auth/CustomerSsoSetup.tsx` | Operator-facing setup screen rendered when `SSO_CUSTOMER_CLIENT_ID/SECRET` are missing. |

## Reused — existing components mounted unchanged

| Component | Where it's used | Why it fits |
|---|---|---|
| `@/Components/JobStateBadge` | `JobShow.tsx`, `Dashboard.tsx` | Same state semantics as mechanic side — no need to mint a customer-facing variant. |
| `@/Components/ui/button` | All `Account/*` pages, `Auth/CustomerSsoSetup.tsx`, `Home.tsx` banner | shadcn baseline; matches portal & mechanic surface. |
| `lucide-react` icons | All `Account/*` pages | Same icon set used everywhere else. |
| `@inertiajs/react` `Head`, `Link`, `router`, `usePage` | All `Account/*` pages | Standard Inertia plumbing. |

## Considered and not used

| Component | Why not |
|---|---|
| `@/Components/PortalLink` | Signed-token specific — embeds a token in the URL. The account portal already has identity from the guard. |
| `@/Pages/Portal/Show.tsx` patterns | The signed-portal page renders a job from the token's perspective (one job); the account dashboard aggregates across jobs/garages — different IA. |
| Form abstraction (e.g. `useForm`) | Line item actions are single-button POSTs — `router.post(...)` is lighter than spinning up a useForm wrapper. |

## Server-derived data shape — known gotchas

| Field | Shape | Gotcha |
|---|---|---|
| `LineItem.price` | string (Laravel `decimal:2` serialization) | Must `Number(price)` before `.toFixed(2)` — same root cause as audit 7 / F2-F3. |
| `JobStage.name` | string (stage identifier, e.g. `pre-inspection`) | There is no separate `state` field on JobStage — early frontend referenced `stage.state.replace(...)` and crashed React. Use `name`. |
| `Media.url` | string (signed GCS URL, minted server-side) | Eloquent's auto-`toArray` does **not** include this; `JobController::serializeJob` calls `GcsService::signedUrl` manually. Frontend treats it as optional (`m.url ? ... : null`) for resilience. |
| `Inertia errors.sso` | string | Read by `Home.tsx` banner — shown when SSO callback bounces (e.g. mechanic callback rejects a customer user). |
| `Inertia ssoPublicUrl` | string | Shared by `HandleInertiaRequests`; `Home.tsx` and `Dashboard.tsx` use it to build the `Log out of SSO` URL. |

## Mobile / accessibility checklist

- [x] Single layout, sidebar collapses on `< md` (matches `GarageLayout` pattern).
- [x] Form errors visible in `decline`/`question` flows (Inertia validation surfaces via `usePage().props.errors`).
- [x] All actions are real `<button>` / `<a>` — no clickable `<div>`s.
- [x] Tap targets ≥ 32px on the action row (`text-xs` is acceptable for the *labels*, but the parent button has padding).
- [ ] Customer name in header on mobile — currently only inside sidebar drawer (playbook audit 8 / F11; not addressed in this batch).

## What new shadcn components are NOT added

Nothing. The portal is built on the existing button + raw Tailwind primitives. If we later need toasts (currently using session flash `alert`/`type`) or modals (currently inline expand-on-click), they go through `@/Components/ui/*` not a parallel `Components/customer/*`.
