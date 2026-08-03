import { expect, test } from '@playwright/test';

/**
 * The auth gate. Covers the playbook's mandatory "guest is redirected to login"
 * case for both guards, and the per-path `redirectGuestsTo` branch in
 * bootstrap/app.php — a regression there sends unauthenticated /account traffic
 * through the mechanic callback, which rejects non-mechanics and can ping-pong.
 */
test.describe('auth gate', () => {
    test('guest hitting the mechanic dashboard is redirected away', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page).not.toHaveURL(/\/dashboard$/);
    });

    for (const path of ['/dashboard', '/vehicles', '/jobs', '/mechanics', '/settings']) {
        test(`guest cannot reach ${path}`, async ({ request }) => {
            const response = await request.get(path, { maxRedirects: 0 });

            expect(response.status()).toBe(302);
            // Mechanic surfaces must not send guests to the customer login.
            expect(response.headers()['location'] ?? '').not.toContain('/account/login');
        });
    }

    test('guest on an /account route is sent to the customer login, not the mechanic one', async ({ request }) => {
        const response = await request.get('/account', { maxRedirects: 0 });

        expect(response.status()).toBe(302);
        expect(response.headers()['location'] ?? '').toContain('/account/login');
    });

    test('unknown portal token is rejected', async ({ request }) => {
        const response = await request.get('/portal/definitely-not-a-real-token');

        expect(response.status()).toBe(404);
    });

    test('health endpoint is public', async ({ request }) => {
        const response = await request.get('/up');

        expect(response.ok()).toBe(true);
    });
});
