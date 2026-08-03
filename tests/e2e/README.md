# Playwright E2E

Mandatory per `inte-playbook/workflow/README.md` → "Playwright E2E Tests (Mandatory — No Exceptions)". A feature is not done until its spec exists and passes.

## Running

```bash
# 1. Stack up + frontend built (Playwright drives the real app, so build, don't rely on the dev server)
docker compose --profile dev up -d
docker compose run --rm npm sh -c "npm run build"

# 2. Seed the fixture (writes storage/app/e2e-fixture.json)
docker compose exec php-fpm php artisan garage:seed-e2e --fresh

# 3. Run the suite
docker compose --profile test run --rm playwright sh -c "npx playwright test"

# Single spec
docker compose --profile test run --rm playwright sh -c "npx playwright test tests/e2e/portal.spec.ts"

# Last HTML report (CI reporter only)
docker compose --profile test run --rm playwright sh -c "npx playwright show-report"
```

Re-run `garage:seed-e2e --fresh` before each full run — the portal specs mutate line-item state.

## Why a separate compose service

The `npm` service is `node:22-alpine`. Playwright's browsers are glibc binaries and `playwright install --with-deps` fails on musl with `apt-get: not found`. The `playwright` service (profile `test`) uses the official image instead.

**The image tag must match the `@playwright/test` version pinned in `package.json`.** A mismatch fails every browser test with `Executable doesn't exist at /ms-playwright/...`. Both are pinned exactly for that reason — bump them together.

`baseURL` defaults to `http://nginx`: the runner is on the `garage` network, so `localhost` there is the container itself, not the app. Override with `E2E_BASE_URL` (e.g. `http://localhost:8085` from the host).

## What is covered, and what is not

| Spec | Covers |
|---|---|
| `landing.spec.ts` | Public landing renders; sign-in CTA is a full-page navigation, not an Inertia XHR; no horizontal scroll on mobile |
| `auth.spec.ts` | Guest redirects for every mechanic surface; `/account/*` goes to the customer login, not the mechanic one; unknown portal token 404s; `/up` is public |
| `portal.spec.ts` | The customer signed-link journey: estimate view, decline confirmation (Poka-Yoke) + required note, persisted decline, cross-token isolation, mobile layout |

**The authenticated mechanic dashboard is not covered by browser specs.** It is behind Inte.Team SSO, and the only way to drive it in a browser would be a test-only login route — an auth bypass shipped in the production codebase. That trade is not worth it, so the mechanic surfaces are covered by the PHPUnit feature suite (`tests/Feature/`, 274 tests including policy, tenant-isolation and state-machine coverage), and E2E covers the guest and token-authenticated journeys, which is where browser-only failures actually live (the Inertia `<Link>` sign-in regression in `docs/app-map.md` is the canonical example — invisible to PHPUnit).

If SSO ever gains a dev-mode client-credentials or impersonation grant, add `dashboard.spec.ts` and use it rather than adding a local bypass.

## Rules

- No `test.only` / `test.skip` / `.fixme` in committed specs — `forbidOnly` fails the build in CI.
- Bug fix → add or extend a spec that reproduces it.
- Modifying an existing flow → update its spec in the same commit.
