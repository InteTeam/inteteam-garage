import { expect, test } from '@playwright/test';

/**
 * The public landing page and its sign-in CTAs.
 *
 * Guards the app-map gotcha that has already broken sign-in once: the CTAs must
 * be plain `<a href="/sign-in">`, not Inertia `<Link>`. A `<Link>` issues an XHR
 * with `X-Inertia: true`; the browser then refuses to follow the cross-origin
 * redirect to SSO and the button silently does nothing. That failure is invisible
 * to PHPUnit — it only shows up in a real browser, which is precisely what the
 * playbook makes Playwright mandatory for.
 */
test.describe('public landing', () => {
    test('renders the marketing page for guests', async ({ page }) => {
        await page.goto('/');

        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.locator('a[href="/sign-in"]').first()).toBeVisible();
    });

    test('sign-in CTA performs a full-page navigation, not an Inertia XHR', async ({ page }) => {
        await page.goto('/');

        const cta = page.locator('a[href="/sign-in"]').first();

        // A plain anchor has no Inertia data attributes and is not intercepted.
        await expect(cta).toHaveAttribute('href', '/sign-in');

        const requests: string[] = [];
        page.on('request', (req) => {
            if (req.url().includes('/sign-in')) {
                requests.push(req.headers()['x-inertia'] ?? 'absent');
            }
        });

        await cta.click();
        await page.waitForLoadState('domcontentloaded');

        // If this were an Inertia <Link>, the request would carry X-Inertia: true.
        expect(requests.every((h) => h === 'absent')).toBe(true);
    });

    test('landing page does not scroll horizontally on a phone viewport', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto('/');

        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        );

        expect(overflow).toBeLessThanOrEqual(1);
    });
});
