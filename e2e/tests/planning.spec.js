import { expect, test } from '@playwright/test';
import { row, signIn, unique } from './support/session.js';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('a project task added to today stays in its project', async ({ page }) => {
    const project = unique('Garden');
    const task = unique('Plant tulips');

    await page.locator('.shell__rail').getByRole('button', { name: 'New project' }).click();
    await page.getByRole('dialog').getByLabel('Name').fill(project);
    await page.getByRole('button', { name: 'Create project' }).click();
    await expect(page.getByRole('heading', { level: 1, name: project })).toBeVisible();

    await page.getByLabel('New task').fill(task);
    await page.getByLabel('New task').press('Enter');
    await row(page, task).getByRole('button', { name: 'Add to today' }).click();
    await expect(row(page, task).getByRole('button', { name: 'Remove from today' })).toBeVisible();

    await page.locator('.shell__rail').getByRole('link', { name: 'Today' }).click();
    await expect(row(page, task)).toBeVisible();
    await expect(row(page, task)).toContainText(project);

    await page.locator('.shell__rail').getByRole('link', { name: project }).click();
    await expect(row(page, task)).toBeVisible();
});

test('adding a task after switching projects keeps the current project list', async ({ page }) => {
    const first = unique('Art');
    const second = unique('Home');
    const firstTask = unique('Sketch');
    const secondTask = unique('Vacuum');

    for (const [project, task] of [[first, firstTask], [second, secondTask]]) {
        await page.locator('.shell__rail').getByRole('button', { name: 'New project' }).click();
        await page.getByRole('dialog').getByLabel('Name').fill(project);
        await page.getByRole('button', { name: 'Create project' }).click();
        await expect(page.getByRole('heading', { level: 1, name: project })).toBeVisible();
        await page.getByLabel('New task').fill(task);
        await page.getByLabel('New task').press('Enter');
        await expect(row(page, task)).toBeVisible();
    }

    await page.reload();
    await expect(row(page, secondTask)).toBeVisible();
    await page.locator('.shell__rail').getByRole('link', { name: first }).click();
    await expect(row(page, firstTask)).toBeVisible();
    await page.locator('.shell__rail').getByRole('link', { name: second }).click();
    await expect(row(page, secondTask)).toBeVisible();

    const added = unique('Dust');
    await page.getByLabel('New task').fill(added);
    await page.getByLabel('New task').press('Enter');
    await expect(row(page, added)).toBeVisible();
    await expect(row(page, secondTask)).toBeVisible();
    await expect(row(page, firstTask)).toHaveCount(0);
});

test('planning a task for tomorrow moves it from today to upcoming', async ({ page }) => {
    const task = unique('Call grandma');
    await page.getByLabel('New task').fill(task);
    await page.getByLabel('New task').press('Enter');
    await expect(row(page, task)).toBeVisible();

    await row(page, task).getByRole('button', { name: 'Plan' }).click();
    await page.getByRole('button', { name: 'Tomorrow' }).click();
    await expect(row(page, task)).toHaveCount(0);

    await page.locator('.shell__rail').getByRole('link', { name: 'Upcoming' }).click();
    await expect(page.locator('.upcoming__day', { hasText: 'Tomorrow' }).locator('.task-row', { hasText: task })).toBeVisible();
});

test('a task with a deadline and no planned day waits on its deadline in upcoming until it is planned', async ({ page }) => {
    const task = unique('Send the invoice');
    await page.goto('/inbox');
    await page.getByLabel('New task').fill(task);
    await page.getByLabel('New task').press('Enter');
    await row(page, task).getByRole('button', { name: task, exact: true }).click();
    const editor = page.getByRole('dialog');
    const deadline = new Date(Date.now() + 2 * 86400000);
    await editor.getByRole('spinbutton').first().click();
    await page.keyboard.type(`${String(deadline.getDate()).padStart(2, '0')}${String(deadline.getMonth() + 1).padStart(2, '0')}${deadline.getFullYear()}`);
    await editor.getByRole('button', { name: 'Save' }).click();
    await expect(editor).toBeHidden();

    await page.locator('.shell__rail').getByRole('link', { name: 'Upcoming' }).click();
    const shown = page.locator('.upcoming .task-row', { hasText: task });
    const day = page.locator('.upcoming__day', { has: page.locator('.task-row', { hasText: task }) });
    await expect(shown).toHaveCount(1);
    await expect(shown).toContainText(`Due ${await day.locator('.upcoming__weekday').textContent()}`);

    await shown.getByRole('button', { name: 'Plan' }).click();
    await page.getByRole('button', { name: 'Tomorrow' }).click();
    await expect(page.locator('.upcoming__day', { has: page.locator('.upcoming__weekday', { hasText: 'Tomorrow' }) }).locator('.task-row', { hasText: task })).toBeVisible();
    await expect(shown).toHaveCount(1);
});

test('a date picked on the calendar plans the task', async ({ page }) => {
    const task = unique('Dentist');
    await page.getByLabel('New task').fill(task);
    await page.getByLabel('New task').press('Enter');

    await row(page, task).getByRole('button', { name: 'Plan' }).click();
    await page.getByRole('button', { name: 'Pick a date' }).click();
    await page.getByRole('button', { name: 'Next month' }).click();
    await page.locator('.plan-menu__calendar .calendar__day:not([data-outside-view])', { hasText: /^15$/ }).click();
    await expect(row(page, task)).toHaveCount(0);
});

test('keyboard shortcut t brings a task back to today', async ({ page }) => {
    const task = unique('Shortcut');
    await page.goto('/inbox');
    await page.getByLabel('New task').fill(task);
    await page.getByLabel('New task').press('Enter');
    await row(page, task).focus();
    await page.keyboard.press('t');
    await expect(row(page, task).getByRole('button', { name: 'Remove from today' })).toBeVisible();
});
