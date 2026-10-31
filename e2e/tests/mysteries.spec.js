import { expect, test } from '@playwright/test';
import { row, signInWithAccount, unique } from './support/session.js';

async function signInAsSleuth(page) {
    const account = unique('mystery').replace(' ', '-');
    await page.goto('/login');
    await signInWithAccount(page, account, { email: `${account}@mossyleaf.test`, name: 'Sleuth' });
    await page.waitForURL('/');
}

function boardOf(id, size) {
    const cells = size * size;

    return {
        investigation: {
            id,
            size,
            rooms: Array.from({ length: cells }, (_, cell) => (cell % size < size / 2 ? 0 : 1)),
            kinds: ['kitchen', 'library'],
            furniture: Array(cells).fill(null),
            people: Array.from({ length: size }, (_, person) => (person === 0 ? 'Vesper' : `Guest ${person}`)),
            genders: Array.from({ length: size }, (_, person) => (person % 2 ? 'male' : 'female')),
            victim: 0,
            clues: [{ kind: 'alone_with_murderer', person: 0 }],
            hiddenClues: size - 1,
            rules: [],
            notes: { placements: Array(size).fill(null), crosses: [] },
            solved: false,
            solution: null,
            murderer: null,
        },
        solved: 0,
    };
}

test('Moss Mysteries is opt-in, its first case shows every clue, and it remembers the marks', async ({ page }) => {
    await signInAsSleuth(page);

    const rail = page.locator('.shell__rail');
    await expect(rail.getByRole('link', { name: 'Mysteries' })).toHaveCount(0);

    await page.goto('/settings');
    const toggle = page.getByRole('switch', { name: 'Moss Mysteries' });
    await expect(toggle).not.toBeChecked();
    const saved = page.waitForResponse((response) => response.url().endsWith('/api/me/mysteries'));
    await toggle.click();
    await expect(toggle).toBeChecked();
    await saved;
    await rail.getByRole('link', { name: 'Mysteries' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Moss Mysteries' })).toBeVisible();

    await page.getByTestId('open-case').click();
    await expect(page.getByTestId('board')).toBeVisible({ timeout: 20_000 });
    await expect(page.getByTestId('cell').first()).toBeVisible();
    await expect(page.getByTestId('person').first()).toBeVisible();
    await expect(page.getByTestId('clue').first()).toBeVisible();
    await expect(page.getByTestId('locked-clue')).toHaveCount(0);
    await expect(page.getByTestId('accuse')).toBeDisabled();

    const first = page.getByTestId('person').first();
    await first.click();
    await expect(first).toHaveAttribute('aria-pressed', 'true');
    await page.locator('[data-test=cell]:not([aria-disabled=true])').first().click();
    await expect(first).toHaveAttribute('aria-label', /placed on [A-N]\d+/);
    await expect(first).toHaveAttribute('aria-pressed', 'false');

    await expect.poll(async () => {
        const body = await (await page.request.get('/api/mystery')).json();
        return body.investigation.notes.placements.filter((cell) => cell !== null).length;
    }).toBe(1);
    await page.reload();
    await expect(page.getByTestId('person').first()).toHaveAttribute('aria-label', /placed on [A-N]\d+/);
});

test('a completed task tells when it revealed a clue', async ({ page }) => {
    await signInAsSleuth(page);
    await page.route('**/api/tasks/*/complete', async (route) => {
        const response = await route.fetch();
        const body = await response.json();
        await route.fulfill({ response, json: { ...body, data: { ...body.data, cluesRevealed: 1 } } });
    });

    const title = unique('Mulch the beds');
    const created = await page.request.post('/api/tasks', { data: { title, plan: 'today', quadrant: 'schedule' } });
    expect(created.ok()).toBeTruthy();
    await page.goto('/');
    await row(page, title).getByRole('checkbox', { name: `Complete “${title}”` }).click();
    await expect(page.getByTestId('toast').filter({ hasText: 'new clue' })).toBeVisible();
});

test('a smaller case opened on another device replaces the board on this one', async ({ page }) => {
    await signInAsSleuth(page);
    expect((await page.request.put('/api/me/mysteries', { data: { enabled: true } })).ok()).toBeTruthy();

    let served = boardOf('large', 14);
    await page.route('**/api/mystery', (route) => route.fulfill({ json: served }));
    await page.goto('/mysteries');
    const comeBack = () => page.evaluate(() => window.dispatchEvent(new Event('focus')));

    await comeBack();
    await expect(page.getByTestId('cell')).toHaveCount(196);
    await page.getByTestId('cell').last().focus();
    await expect(page.getByTestId('board-status')).toHaveText('N14 · Library');

    served = boardOf('small', 6);
    await comeBack();
    await expect(page.getByTestId('cell')).toHaveCount(36);
    await expect(page.getByTestId('board-status')).toHaveText('Tap a square to see its room and furniture.');
});

test('the board rules of a case stand above its clues', async ({ page }) => {
    await signInAsSleuth(page);
    expect((await page.request.put('/api/me/mysteries', { data: { enabled: true } })).ok()).toBeTruthy();

    const served = boardOf('ruled', 6);
    served.investigation.rules = [{ kind: 'room_cap', number: 2 }, { kind: 'pairing', gender: 'male' }];
    await page.route('**/api/mystery', (route) => route.fulfill({ json: served }));
    await page.goto('/mysteries');
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));

    await expect(page.getByRole('heading', { name: 'Board rules' })).toBeVisible();
    await expect(page.getByTestId('board-rule')).toHaveText(['No room held more than 2 people.', 'Every man shared his room with a woman.']);
    await expect(page.getByTestId('clue')).toHaveCount(1);
});

test('everyone on the case shows whether they are a woman or a man', async ({ page }) => {
    await signInAsSleuth(page);
    expect((await page.request.put('/api/me/mysteries', { data: { enabled: true } })).ok()).toBeTruthy();

    await page.route('**/api/mystery', (route) => route.fulfill({ json: boardOf('gendered', 6) }));
    await page.goto('/mysteries');
    await page.evaluate(() => window.dispatchEvent(new Event('focus')));

    const people = page.getByTestId('person');
    await expect(people.nth(0)).toHaveAttribute('aria-label', 'Vesper (woman), the victim, not placed yet');
    await expect(people.nth(1)).toHaveAttribute('aria-label', 'Guest 1 (man), not placed yet');
    await expect(people.nth(0).getByTestId('gender')).toHaveAttribute('title', 'Woman');
    await expect(people.nth(1).getByTestId('gender')).toHaveAttribute('title', 'Man');
});

test('a wrong accusation is refused without saying what is wrong', async ({ page }) => {
    await signInAsSleuth(page);
    expect((await page.request.put('/api/me/mysteries', { data: { enabled: true } })).ok()).toBeTruthy();
    expect((await page.request.post('/api/mystery/investigations')).ok()).toBeTruthy();
    await page.goto('/mysteries');

    const people = page.getByTestId('person');
    const cells = page.locator('[data-test=cell]:not([aria-disabled=true])');
    for (let person = 0; person < await people.count(); person += 1) {
        await people.nth(person).click();
        await cells.nth(person).click();
    }
    await page.getByTestId('suspect').first().click();
    await page.getByTestId('accuse').click();

    await expect(page.getByTestId('toast').filter({ hasText: 'Look again' })).toBeVisible();
    await expect(page.getByTestId('verdict')).toHaveCount(0);
});
