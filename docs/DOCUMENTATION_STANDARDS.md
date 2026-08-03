# Documentation Standards

Templates and conventions for docs in this repo. Required reading per `inte-playbook/laravel/README.md` Step 0.

## What lives where

| Path | Purpose | Updated when |
|---|---|---|
| `CLAUDE.md` | Project rules, docker commands, conventions | A convention or command changes |
| `.sop.md` | Step-by-step procedures per change type | A procedure changes |
| `docs/app-map.md` | "What lives where" + gotchas | **Last step of every feature** |
| `docs/planning.md` | Domain model, state machine, i18n, Poka-Yoke | A domain decision changes |
| `docs/tasks.md` | Phased checklist | Progress (verify before trusting a checkbox) |
| `docs/FEATURE_DESIGN_CHECKLIST.md` | Working template, copied per feature | The bar for "done" changes |
| `docs/DATABASE_CONVENTIONS.md` | FK types, PK types, index rules | A schema convention changes |
| `docs/WORKFLOW_ENFORCEMENT.md` | Mandatory steps + invariants | An invariant is added |
| `docs/features/{name}/` | Per-feature README + architecture + COMPONENT_INVENTORY | That feature changes |
| `docs/database/migrations/NNN_*.md` | One doc per migration | Every migration, no exceptions |
| `docs/api/portal.yaml` | OpenAPI for the customer portal | A portal endpoint changes |
| `tests/e2e/README.md` | How to run E2E and what it covers | Specs are added or coverage changes |

## The rule that matters most

**Write down the "why", not the "what".** The "what" is in the code and goes stale; the "why" is not recoverable from the code and is what the next person needs.

Concretely — this is the bar:

> `whereDate` over `whereBetween` for date columns under SQLite. SQLite stores dates as TEXT and Laravel's `'date'` cast persists them as `'YYYY-MM-DD 00:00:00'`; `whereBetween` then compares lexicographically and silently drops the upper-boundary day.

Not this:

> Uses `whereDate` to filter by date.

## Gotchas (`docs/app-map.md`)

The highest-value section in the repo. A gotcha earns a place when it is:

- **non-obvious** — a reasonable engineer would get it wrong
- **load-bearing** — getting it wrong breaks something, often silently
- **not greppable** — you cannot discover it by reading the file it affects

Format: bold one-line claim, then the mechanism, then the consequence, then where the test is.

Do **not** put in `app-map.md`: individual route names, method signatures, component file paths. They go stale within weeks and are trivially greppable.

**A gotcha must describe reality.** `app-map.md` claimed `EVENT_ESTIMATE_SENT` was written by the estimate send flow when nothing in the codebase wrote it — a doc that is confidently wrong is worse than a missing one, because it stops the next person from checking. When you edit behaviour, edit the claim.

## Migration docs (`docs/database/migrations/NNN_*.md`)

Sequential numbering — find the last with `ls docs/database/migrations/ | sort -n | tail -1`. One doc per migration. Required sections:

1. **Why** — the problem, not the schema
2. **Schema** — table of column, type, null, default, notes
3. **Indexes** — name and columns
4. **Foreign keys** — including *why not*, if there is no FK
5. **Nullability** — why a column is nullable, if it is
6. **How it gets populated** — for columns not set by ordinary CRUD
7. **Model** — `$fillable`, casts, whether it is audited
8. **Rollback** — what `down()` does and whether it is safe
9. **Tests** — the specific test names covering it

## Feature docs

| File | Contents |
|---|---|
| `README.md` | Overview, user stories with acceptance criteria, the embedded design checklist |
| `architecture.md` | Affected tables/models/services/controllers/pages, tenancy design, indexes, caching, queues, external calls |
| `COMPONENT_INVENTORY.md` | Existing components to REUSE (with import paths), new ones to CREATE, import map |

## Env vars

Every var in `.env.example` gets a comment saying **what breaks if it is wrong or missing** — not what it is. `SSO_PUBLIC_URL=` alone is useless; the note that a container's `localhost` is not the host's is the whole point.

An env var referenced in `config/` but absent from `.env.example` is a bug. Check with:

```bash
comm -23 \
  <(grep -rhoP "env\('\K[A-Z0-9_]+" config/ | sort -u) \
  <(grep -oP "^\K[A-Z0-9_]+(?==)" .env.example | sort -u)
```

Framework defaults (`REDIS_*`, `MAIL_*`, `AWS_*`, `SESSION_*` …) are expected in that output; anything app-specific is not.

## Style

- Markdown tables for anything enumerable
- Fenced code blocks with a language tag
- Absolute repo-relative paths (`app/Services/CrmApiService.php`), never "the CRM service file"
- Reference tests by their method name so a claim can be verified
- Convert relative dates to absolute ("2026-08-03", not "last week")
