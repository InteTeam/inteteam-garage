import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright is mandatory per inte-playbook/workflow/README.md → "Playwright E2E
 * Tests (Mandatory — No Exceptions)".
 *
 * baseURL defaults to `http://nginx` because the suite runs inside the `npm`
 * compose service, which shares the `garage` network with the nginx container —
 * `localhost` there is the npm container itself, not the app. Override with
 * E2E_BASE_URL to run against the host (`http://localhost:8085`) or staging.
 *
 * Prerequisite: `php artisan garage:seed-e2e --fresh` writes
 * storage/app/e2e-fixture.json, which the specs read. See tests/e2e/README.md.
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI, // fails the build if test.only is committed
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? [['html'], ['list']] : 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://nginx',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
