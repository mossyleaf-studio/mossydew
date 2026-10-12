import { expect, test } from '@playwright/test';
import { signInWithAccount, unique } from './support/session.js';

async function newGardener(browser, name) {
    const context = await browser.newContext();
    const page = await context.newPage();
    const account = unique(name.toLowerCase()).replace(' ', '-');
    await page.goto('/login');
    await signInWithAccount(page, account, { email: `${account}@mossyleaf.test`, name });
    await page.waitForURL('/');
    return page;
}

test('an invitation link makes two gardeners friends', async ({ browser }) => {
    const fern = await newGardener(browser, 'Fern');
    const ivy = await newGardener(browser, 'Ivy');

    await fern.goto('/friends');
    await fern.getByRole('button', { name: 'Create an invitation link' }).click();
    const link = await fern.locator('.friends__invite code').textContent();

    await ivy.goto(link);
    await expect(ivy.getByText('Fern invites you to be friends on MossyDew.', { exact: false })).toBeVisible();
    await ivy.getByRole('button', { name: 'Accept' }).click();
    await ivy.waitForURL('/friends');
    await expect(ivy.locator('.friends__card', { hasText: 'Fern' })).toBeVisible();

    await fern.reload();
    await expect(fern.locator('.friends__card', { hasText: 'Ivy' })).toContainText('0 XP');

    await ivy.goto(link);
    await expect(ivy.getByText('This invitation has already been used.', { exact: false })).toBeVisible();
});
