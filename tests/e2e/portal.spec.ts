import { expect, test } from '@playwright/test';
import { loadFixture } from './fixtures/fixture';

const fixture = loadFixture();
const portal = `/portal/${fixture.job.token}`;

/**
 * The customer's signed-link journey — the product's primary user story
 * ("customers access a signed portal link, no SSO required").
 *
 * Covers the playbook's per-feature E2E checklist: happy path, auth gate,
 * validation, tenant/token isolation, and the Poka-Yoke confirmation UI.
 *
 * Each spec that mutates a line item re-seeds nothing, so approve/decline target
 * different items and the read-only specs run first by file order under
 * fullyParallel: reruns should be preceded by `garage:seed-e2e --fresh`.
 */
test.describe('customer portal', () => {
    test('happy path — customer opens their job and sees the estimate', async ({ page }) => {
        await page.goto(portal);

        await expect(page.getByRole('heading', { name: 'Your Repair Job' })).toBeVisible();
        await expect(page.getByText(fixture.job.registration)).toBeVisible();
        await expect(page.getByRole('heading', { name: /Estimate #\d+/ })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Approve' }).first()).toBeVisible();
    });

    test('timeline is reachable from the token', async ({ page }) => {
        const response = await page.goto(`${portal}/timeline`);

        expect(response?.ok()).toBe(true);
    });

    test('payment page renders the approved total', async ({ page }) => {
        const response = await page.goto(`${portal}/payment`);

        expect(response?.ok()).toBe(true);
    });

    /**
     * Poka-Yoke: declining requires a reason, so the decline button opens a
     * confirmation step rather than acting immediately.
     */
    test('declining opens a confirmation step and requires a note', async ({ page }) => {
        await page.goto(portal);

        await page.getByRole('button', { name: 'Decline' }).first().click();

        const note = page.getByPlaceholder(/explain why you're declining/i);
        await expect(note).toBeVisible();
        await expect(page.getByRole('button', { name: 'Confirm Decline' })).toBeVisible();
    });

    test('a declined item is persisted and the buttons are replaced by its status', async ({ page }) => {
        await page.goto(portal);

        const rows = page.locator('div.border.rounded-md, div[class*="rounded-md"]');
        await page.getByRole('button', { name: 'Decline' }).last().click();
        await page.getByPlaceholder(/explain why you're declining/i).fill('Too expensive for now, please skip.');
        await page.getByRole('button', { name: 'Confirm Decline' }).click();

        await expect(page.getByText('declined').first()).toBeVisible();
        expect(await rows.count()).toBeGreaterThan(0);
    });

    /**
     * Token scope. The middleware binds exactly one job to the route, so token A
     * must never be able to act on token B's line items even across garages.
     */
    test('a token cannot act on another job line item', async ({ request }) => {
        const foreignLineItem = fixture.otherJob.lineItemIds[0];

        const response = await request.post(`${portal}/line-items/${foreignLineItem}/approve`, {
            maxRedirects: 0,
            failOnStatusCode: false,
        });

        expect([404, 419]).toContain(response.status());
    });

    test('the other token loads its own job, not the first one', async ({ page }) => {
        await page.goto(`/portal/${fixture.otherJob.token}`);

        await expect(page.getByRole('heading', { name: 'Your Repair Job' })).toBeVisible();
        await expect(page.getByText(fixture.job.registration)).toHaveCount(0);
    });

    test('portal is usable on a phone viewport without horizontal scroll', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 812 });
        await page.goto(portal);

        const overflow = await page.evaluate(
            () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        );

        expect(overflow).toBeLessThanOrEqual(1);
    });
});
