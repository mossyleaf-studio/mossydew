import { expect, test } from '@playwright/test';
import { row, signIn, unique } from './support/session.js';

test('a drawing split into steps completes once every step is done', async ({ page }) => {
    await signIn(page);
    const project = unique('Drawings');
    const drawing = unique('Fox drawing');
    const sketch = unique('Sketch');
    const colouring = unique('Colouring');

    await page.locator('.shell__rail').getByRole('button', { name: 'New project' }).click();
    await page.getByRole('dialog').getByLabel('Name').fill(project);
    await page.getByRole('button', { name: 'Create project' }).click();
    await expect(page.getByRole('heading', { level: 1, name: project })).toBeVisible();
    await page.getByLabel('New task').fill(drawing);
    await page.getByLabel('New task').press('Enter');

    await row(page, drawing).getByRole('button', { name: drawing, exact: true }).click();
    const editor = page.getByRole('dialog');
    for (const step of [sketch, colouring]) {
        await editor.getByLabel('New subtask').fill(step);
        await editor.getByLabel('New subtask').press('Enter');
        await expect(editor.getByRole('checkbox', { name: `Complete “${step}”` })).toBeVisible();
    }
    await expect(editor).toBeVisible();
    await editor.getByRole('button', { name: 'Cancel' }).click();
    await expect(editor).toBeHidden();

    await expect(row(page, drawing)).toContainText('0/2');
    await expect(row(page, drawing).getByRole('checkbox', { name: `“${drawing}” completes once its subtasks are done` })).toBeDisabled();
    await expect(page.locator('.task-list__subtasks .task-row')).toHaveCount(2);

    for (const step of [sketch, colouring]) {
        const completed = page.waitForResponse((response) => response.url().endsWith('/complete') && response.ok());
        await row(page, step).getByRole('checkbox', { name: `Complete “${step}”` }).click();
        await completed;
    }

    const done = page.locator('.shell__main .task-row--done', { has: page.locator('.task-row__title', { hasText: drawing }) });
    await expect(done).toContainText('2/2');
    await expect(done.locator('.drop-check')).toHaveAttribute('aria-checked', 'true');
    await expect(page.locator('.task-list__subtasks .task-row--done')).toHaveCount(2);
});

test('steps are added from the task menu, dragged onto a task and made tasks again', async ({ page }) => {
    await signIn(page);
    const project = unique('Comics');
    const page1 = unique('Page one');
    const ink = unique('Ink');
    const sketch = unique('Sketch');
    const letters = unique('Letters');

    await page.locator('.shell__rail').getByRole('button', { name: 'New project' }).click();
    await page.getByRole('dialog').getByLabel('Name').fill(project);
    await page.getByRole('button', { name: 'Create project' }).click();
    await expect(page.getByRole('heading', { level: 1, name: project })).toBeVisible();
    for (const title of [page1, ink]) {
        await page.getByLabel('New task').fill(title);
        await page.getByLabel('New task').press('Enter');
        await expect(row(page, title)).toBeVisible();
    }
    const subtasks = page.locator('.task-list__subtasks .task-row');

    await row(page, page1).getByRole('button', { name: `More for “${page1}”` }).click();
    await page.getByRole('menuitem', { name: 'Add a subtask' }).click();
    await page.getByLabel('New subtask').fill(sketch);
    await page.getByLabel('New subtask').press('Enter');
    await expect(subtasks.filter({ hasText: sketch })).toBeVisible();
    await page.getByLabel('New subtask').press('Escape');
    await expect(page.getByLabel('New subtask')).toBeHidden();

    await page.locator('.task-list__subtasks').getByRole('button', { name: 'Add a subtask' }).click();
    await page.getByLabel('New subtask').fill(letters);
    await page.getByLabel('New subtask').press('Enter');
    await expect(subtasks).toHaveCount(2);
    await expect(row(page, page1)).toContainText('0/2');

    await row(page, ink).dragTo(row(page, page1).getByRole('button', { name: page1, exact: true }));
    await expect(subtasks.filter({ hasText: ink })).toBeVisible();
    await expect(row(page, page1)).toContainText('0/3');

    await subtasks.filter({ hasText: ink }).getByRole('button', { name: `More for “${ink}”` }).click();
    await page.getByRole('menuitem', { name: 'Make it a task' }).click();
    await expect(subtasks).toHaveCount(2);
    await expect(row(page, ink)).toBeVisible();
    await expect(row(page, page1)).toContainText('0/2');
});

test('moving steps between tasks leaves every task once on the page', async ({ page }) => {
    await signIn(page);
    const project = unique('Zine');
    const [cover, pages, binding] = [unique('Cover'), unique('Pages'), unique('Binding')];
    const fold = unique('Fold');

    await page.locator('.shell__rail').getByRole('button', { name: 'New project' }).click();
    await page.getByRole('dialog').getByLabel('Name').fill(project);
    await page.getByRole('button', { name: 'Create project' }).click();
    await expect(page.getByRole('heading', { level: 1, name: project })).toBeVisible();
    for (const title of [cover, pages, binding]) {
        await page.getByLabel('New task').fill(title);
        await page.getByLabel('New task').press('Enter');
        await expect(row(page, title)).toBeVisible();
    }
    const subtasks = page.locator('.task-list__subtasks .task-row');

    await row(page, pages).getByRole('button', { name: `More for “${pages}”` }).click();
    await page.getByRole('menuitem', { name: 'Add a subtask' }).click();
    await page.getByLabel('New subtask').fill(fold);
    await page.getByLabel('New subtask').press('Enter');
    await expect(subtasks.filter({ hasText: fold })).toBeVisible();
    await page.getByLabel('New subtask').press('Escape');

    await subtasks.filter({ hasText: fold }).dragTo(row(page, cover).getByRole('button', { name: cover, exact: true }));
    await expect(row(page, cover)).toContainText('0/1');
    await expect(row(page, pages)).toHaveCount(1);
    await expect(row(page, fold)).toHaveCount(1);

    await row(page, binding).dragTo(row(page, pages).getByRole('button', { name: pages, exact: true }));
    await expect(subtasks.filter({ hasText: binding })).toBeVisible();
    await subtasks.filter({ hasText: binding }).getByRole('button', { name: `More for “${binding}”` }).click();
    await page.getByRole('menuitem', { name: 'Make it a task' }).click();
    await expect(subtasks.filter({ hasText: binding })).toHaveCount(0);

    for (const title of [cover, pages, binding, fold]) {
        await expect(row(page, title)).toHaveCount(1);
    }
    await expect(subtasks).toHaveText([new RegExp(fold)]);
});

test('a task with subtasks folds them away and remembers it', async ({ page }) => {
    await signIn(page);
    const drawing = unique('Owl drawing');
    const sketch = unique('Sketch');
    await page.goto('/inbox');
    await page.getByLabel('New task').fill(drawing);
    await page.getByLabel('New task').press('Enter');
    await row(page, drawing).getByRole('button', { name: drawing, exact: true }).click();
    const editor = page.getByRole('dialog');
    await editor.getByLabel('New subtask').fill(sketch);
    await editor.getByLabel('New subtask').press('Enter');
    await expect(editor.getByRole('checkbox', { name: `Complete “${sketch}”` })).toBeVisible();
    await editor.getByRole('button', { name: 'Cancel' }).click();

    await expect(row(page, sketch)).toBeVisible();
    await row(page, drawing).getByRole('button', { name: `Hide the subtasks of “${drawing}”` }).click();
    await expect(row(page, sketch)).toBeHidden();
    await page.reload();
    await expect(row(page, drawing)).toContainText('0/1');
    await expect(row(page, sketch)).toBeHidden();

    await row(page, drawing).focus();
    await page.keyboard.press('ArrowRight');
    await expect(row(page, sketch)).toBeVisible();
});
