import { expect, test } from '@playwright/test';
import { signInWithAccount, unique } from './support/session.js';

test('a project row is dragged with the mouse straight away without opening it', async ({ page }) => {
    const account = unique('hypnum').replace(' ', '-');
    await page.goto('/login');
    await signInWithAccount(page, account, { email: `${account}@mossyleaf.test`, name: 'Moss' });
    await page.waitForURL('/');

    for (const project of ['Garden', 'Attic']) {
        await page.locator('.shell__rail').getByRole('button', { name: 'New project' }).click();
        await page.getByRole('dialog').getByLabel('Name').fill(project);
        await page.getByRole('button', { name: 'Create project' }).click();
        await expect(page.getByRole('heading', { level: 1, name: project })).toBeVisible();
    }

    await page.goto('/projects');
    const names = page.locator('.shell__main .projects__name');
    await expect(names).toHaveText(['Garden', 'Attic', 'Inbox']);
    const first = await page.locator('.shell__main .projects__link', { hasText: 'Garden' }).boundingBox();
    const second = await page.locator('.shell__main .projects__link', { hasText: 'Attic' }).boundingBox();
    const x = second.x + second.width / 2;
    const from = second.y + second.height / 2;
    const to = first.y + 4;

    await page.mouse.move(x, from);
    await page.mouse.down();
    for (let step = 1; step <= 20; step += 1) {
        await page.mouse.move(x, from + ((to - from) * step) / 20);
        await page.waitForTimeout(40);
    }
    await page.waitForTimeout(100);
    await page.mouse.up();

    await expect(names).toHaveText(['Attic', 'Garden', 'Inbox']);
    await expect(page.locator('.shell__rail .nav-menu__link--project')).toHaveText(['Attic', 'Garden']);
    await expect(page).toHaveURL(/\/projects$/);
});

test('projects keep the order chosen with the keyboard on the projects page', async ({ page }) => {
    const account = unique('sphagnum').replace(' ', '-');
    await page.goto('/login');
    await signInWithAccount(page, account, { email: `${account}@mossyleaf.test`, name: 'Moss' });
    await page.waitForURL('/');

    for (const project of ['Garden', 'Attic']) {
        await page.locator('.shell__rail').getByRole('button', { name: 'New project' }).click();
        await page.getByRole('dialog').getByLabel('Name').fill(project);
        await page.getByRole('button', { name: 'Create project' }).click();
        await expect(page.getByRole('heading', { level: 1, name: project })).toBeVisible();
    }

    const rail = page.locator('.shell__rail .nav-menu__link--project');
    await expect(rail).toHaveText(['Garden', 'Attic']);

    await page.locator('.shell__rail').getByRole('link', { name: 'Projects', exact: true }).click();
    await expect(page.getByText('Drag projects into the order you want')).toBeVisible();
    const attic = page.locator('.shell__main').getByRole('link', { name: /Attic/ });
    await attic.focus();
    await attic.press('Alt+ArrowUp');

    await expect(attic).toBeFocused();
    await expect(page.locator('.shell__main .projects__name')).toHaveText(['Attic', 'Garden', 'Inbox']);
    await expect(rail).toHaveText(['Attic', 'Garden']);

    await page.reload();
    await expect(rail).toHaveText(['Attic', 'Garden']);
    await expect(page.locator('.shell__main .projects__name')).toHaveText(['Attic', 'Garden', 'Inbox']);
});
