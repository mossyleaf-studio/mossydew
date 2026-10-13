import { expect, test } from '@playwright/test';
import { row, signInWithAccount, unique } from './support/session.js';

test.beforeEach(async ({ page }) => {
    const account = unique('moss').replace(' ', '-');
    await page.goto('/login');
    await signInWithAccount(page, account, { email: `${account}@mossyleaf.test`, name: 'Moss' });
    await page.waitForURL('/');
});

test('selected tasks are sorted, planned and completed together', async ({ page }) => {
    const tasks = [unique('Rake'), unique('Prune'), unique('Mulch')];
    await page.goto('/inbox');
    for (const task of tasks) {
        await page.getByLabel('New task').fill(task);
        await page.getByLabel('New task').press('Enter');
        await expect(row(page, task)).toBeVisible();
    }

    await row(page, tasks[0]).locator('.task-row__title').click({ modifiers: ['ControlOrMeta'] });
    await row(page, tasks[1]).locator('.task-row__title').click();
    await row(page, tasks[2]).focus();
    await page.keyboard.press('x');
    const bar = page.getByRole('toolbar', { name: 'Selected tasks' });
    await expect(bar).toContainText('3 selected');
    await expect(row(page, tasks[0])).not.toHaveAttribute('draggable', 'true');
    await expect(page.getByRole('dialog')).toHaveCount(0);

    await bar.getByRole('button', { name: 'Matrix' }).click();
    await page.getByRole('menuitem', { name: /Plant/ }).click();
    await expect(page.getByTestId('toast').getByText('Done for 3 tasks.')).toBeVisible();
    await expect(bar).toBeHidden();

    for (const task of tasks.slice(0, 2)) await row(page, task).locator('.task-row__title').click({ modifiers: ['Shift'] });
    await page.getByRole('toolbar', { name: 'Selected tasks' }).getByRole('button', { name: 'Complete' }).click();
    await expect(row(page, tasks[0])).toBeHidden();
    await expect(row(page, tasks[1])).toBeHidden();
    await expect(row(page, tasks[2])).toBeVisible();

    await row(page, tasks[2]).focus();
    await page.keyboard.press('x');
    await page.keyboard.press('Escape');
    await expect(page.getByRole('toolbar', { name: 'Selected tasks' })).toBeHidden();
});
