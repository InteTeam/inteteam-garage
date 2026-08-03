import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

export interface E2eFixture {
    appUrl: string;
    garage: { id: string; slug: string; name: string };
    job: {
        id: string;
        state: string;
        registration: string;
        token: string;
        estimateId: string;
        lineItemIds: string[];
    };
    otherJob: { id: string; token: string; lineItemIds: string[] };
}

// Resolved from the Playwright root (the directory holding playwright.config.ts),
// not `__dirname` — package.json sets "type": "module", so CommonJS globals are
// not defined in these files.
const FIXTURE_PATH = resolve(process.cwd(), 'storage/app/e2e-fixture.json');

/**
 * Reads the fixture written by `php artisan garage:seed-e2e`.
 *
 * The mechanic dashboard sits behind Inte.Team SSO, and the alternative to this
 * file — a test-only login route — would put an auth bypass in the production
 * codebase. So the specs cover what needs no session: the public landing page,
 * the auth gate itself, and the signed-token customer portal (a real
 * token-authenticated journey, not a shortcut).
 */
export function loadFixture(): E2eFixture {
    try {
        return JSON.parse(readFileSync(FIXTURE_PATH, 'utf-8')) as E2eFixture;
    } catch {
        throw new Error(
            `E2E fixture not found at ${FIXTURE_PATH}.\n` +
                'Run it first:\n' +
                '  docker compose exec php-fpm php artisan garage:seed-e2e --fresh',
        );
    }
}
