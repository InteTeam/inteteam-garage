# Feature Design Checklist

Copy this into `docs/features/{name}/README.md` and check items off **during** the work, not after. Required reading per `inte-playbook/laravel/README.md` Step 0.

## 0 — Context

- [ ] Read `docs/app-map.md` for the area being touched (routes, services, gotchas)
- [ ] Read existing `docs/features/*/` docs that overlap
- [ ] Read `CLAUDE.md` + `.sop.md` + `docs/DATABASE_CONVENTIONS.md`
- [ ] Identified which playbook section applies (`laravel/`, `react/`, `docker/`)

## 1 — Requirements

- [ ] User stories with acceptance criteria written
- [ ] Poka-Yoke analysis: what mistake is being made impossible? (`docs/planning.md`)
- [ ] Which of the two audiences: mechanic dashboard, customer portal, or both
- [ ] Locale impact: does any copy reach a customer whose locale may differ from the mechanic's?

## 2 — Data

- [ ] Every new table uses ULID PK, `garage_id` FK, `HasGarageScope`
- [ ] FK types: `foreignId` for `users`, `foreignUlid` for everything else
- [ ] Indexes planned — at minimum `(garage_id, created_at)`; add `(garage_id, status)` where a status is filtered
- [ ] Migration doc created at `docs/database/migrations/NNN_{table}.md` (next sequential number)
- [ ] Destructive or `information_schema` migrations are SQLite-guarded (tests run on `:memory:`)
- [ ] Append-only audit tables: `$timestamps = false`, no `SoftDeletes`, FKs `RESTRICT`

## 3 — Components (before writing any new one)

- [ ] `docs/features/{name}/COMPONENT_INVENTORY.md` written
- [ ] Existing components listed to REUSE, with import paths
- [ ] Only genuinely new components listed to CREATE
- [ ] Dark mode handled — every colour utility has a `dark:` counterpart

## 4 — Backend

- [ ] Controller under 150 lines, `final`, authorize → delegate → respond
- [ ] Service under 250 lines, `final`, owns all queries and mutations
- [ ] Mutations wrapped in `DB::transaction()`
- [ ] `declare(strict_types=1)` and explicit return types everywhere
- [ ] Form Request per write, array-syntax rules
- [ ] **Model enum constants enforced with `Rule::in(Model::CONSTANTS)`, not bare `'string'`**
- [ ] Server-derived fields (`garage_id`, `sort_order`, `state`) are NOT accepted from the client
- [ ] Policy method added and attached via `#[UsePolicy]`
- [ ] Flash messages: `->with(['alert' => 'The X was Y.', 'type' => 'success'])`
- [ ] Anything over 5s is queued

## 5 — External services

- [ ] The remote endpoint **exists and is deployed** — verified, not assumed
- [ ] Failure policy chosen: degrade-and-log (side effects) vs. surface-an-error (user-blocking)
- [ ] No external failure can propagate as a 500 out of an already-committed domain write
- [ ] Tenancy passed through (`crm_company_id`) where the remote API is company-scoped
- [ ] Contract recorded in `docs/features/crm-integration/contract.md` if CRM-facing

## 6 — Tests

- [ ] Guest → redirected to login
- [ ] Wrong role → 403
- [ ] Valid data → `assertDatabaseHas`
- [ ] Invalid data → `assertSessionHasErrors`
- [ ] Soft delete → `assertSoftDeleted`, not `assertDatabaseMissing`
- [ ] **Cross-garage isolation** → another garage's record is not reachable
- [ ] Unconfigured-secret / unconfigured-service path fails closed, not open
- [ ] Outbound HTTP assertions cover the **request** (path, headers, params), not just a faked response
- [ ] **Playwright spec written and passing** — happy path, auth gate, validation, isolation, Poka-Yoke UI state

## 7 — Ship

- [ ] `php artisan test` (after `config:clear`)
- [ ] `pint --dirty`
- [ ] `phpstan analyse --memory-limit=512M`
- [ ] `npm run build`
- [ ] `npx playwright test` — no `test.only` / `test.skip` / `.fixme` committed
- [ ] Any new env var added to `.env.example` **with a comment saying what breaks without it**
- [ ] `docs/app-map.md` updated: new route group, role, event, service, or gotcha
- [ ] Audited against the relevant `inte-playbook` section
