import { expect, test } from '@playwright/test';
import { row, signIn, unique } from './support/session.js';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('a Super Productivity backup brings its projects and quadrants, once', async ({ page }) => {
    const project = unique('Musique');
    const task = unique('Mix the single');
    const id = unique('sp').replace(' ', '-');
    const backup = {
        task: { ids: [id], entities: { [id]: { id, title: task, isDone: false, projectId: 'music', tagIds: ['EM_URGENT', 'EM_IMPORTANT'], subTaskIds: [] } } },
        project: { ids: ['music'], entities: { music: { id: 'music', title: project } } },
        archiveYoung: { task: { ids: [], entities: {} } },
    };
    const file = { name: 'super-productivity.json', mimeType: 'application/json', buffer: Buffer.from(JSON.stringify(backup)) };

    await page.locator('.shell__rail').getByRole('link', { name: 'Settings' }).click();
    await page.locator('input[type="file"]').setInputFiles(file);
    await expect(page.getByTestId('toast').getByText('Imported: 1 · New projects: 1 · Already there: 0')).toBeVisible();

    await page.locator('input[type="file"]').setInputFiles(file);
    await expect(page.getByTestId('toast').getByText('Imported: 0 · New projects: 0 · Already there: 1')).toBeVisible();

    await page.locator('.shell__rail').getByRole('link', { name: project }).click();
    await expect(row(page, task)).toBeVisible();
    await page.locator('.shell__rail').getByRole('link', { name: 'Matrix' }).click();
    await expect(page.locator('.matrix__zone--do_first', { hasText: task })).toBeVisible();
});
