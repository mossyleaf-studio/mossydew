import { expect, test } from '@playwright/test';
import { row, signInWithAccount, unique } from './support/session.js';

test('all tasks folds the subtasks of a task until it is opened', async ({ page }) => {
    const account = unique('bryum').replace(' ', '-');
    await page.goto('/login');
    await signInWithAccount(page, account, { email: `${account}@mossyleaf.test`, name: 'Moss' });
    await page.waitForURL('/');

    await page.locator('.shell__rail').getByRole('button', { name: 'New project' }).click();
    await page.getByRole('dialog').getByLabel('Name').fill('Album');
    await page.getByRole('button', { name: 'Create project' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Album' })).toBeVisible();
    for (const title of ['Release', 'Liner notes']) {
        await page.getByLabel('New task').fill(title);
        await page.getByLabel('New task').press('Enter');
        await expect(row(page, title)).toBeVisible();
    }

    await row(page, 'Release').getByRole('button', { name: 'Release', exact: true }).click();
    const editor = page.getByRole('dialog');
    const steps = ['Step 1', 'Step 2', 'Step 3', 'Step 4', 'Step 5', 'Step 6'];
    for (const step of steps) {
        await editor.getByLabel('New subtask').fill(step);
        await editor.getByLabel('New subtask').press('Enter');
        await expect(editor.getByRole('checkbox', { name: `Complete “${step}”` })).toBeVisible();
    }
    await editor.getByRole('button', { name: 'Cancel' }).click();
    await expect(editor).toBeHidden();

    await page.locator('.shell__rail').getByRole('link', { name: 'All tasks' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'All tasks' })).toBeVisible();
    const group = page.locator('.task-group', { has: page.locator('.task-group__name', { hasText: 'Album' }) });
    await expect(group.locator('.task-group__count')).toHaveText('2 tasks · 6 subtasks');
    await expect(group.locator('.task-row')).toHaveCount(2);
    const parent = group.locator('.task-row', { hasText: 'Release' });
    await expect(parent).toContainText('0/6');

    await parent.getByRole('button', { name: 'Show the subtasks of “Release”' }).click();
    await expect(group.locator('.task-row--nested')).toHaveText(steps.slice(0, 5).map((step) => new RegExp(step)));
    await group.getByRole('button', { name: 'Show all 6' }).click();
    await expect(group.locator('.task-row--nested')).toHaveCount(6);
    await expect(group.locator('.task-row--nested', { hasText: 'Step 6' })).toBeVisible();

    const completed = page.waitForResponse((response) => response.url().endsWith('/complete') && response.ok());
    await group.getByRole('checkbox', { name: 'Complete “Liner notes”' }).click();
    await completed;
    await expect(group.locator('.task-row', { hasText: 'Liner notes' })).toHaveCount(0);
    await expect(group.locator('.task-group__count')).toHaveText('1 task · 6 subtasks');
});
