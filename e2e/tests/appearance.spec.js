import { expect, test } from '@playwright/test';
import { signIn } from './support/session.js';

test('the cozy design and Phosphor icons stay chosen after a reload', async ({ page }) => {
    await signIn(page);
    await page.goto('/settings');

    const designs = page.getByRole('radiogroup', { name: 'Design' });
    const icons = page.getByRole('radiogroup', { name: 'Icons' });
    await designs.getByRole('radio', { name: 'Cozy' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-design', 'cozy');
    await icons.getByRole('radio', { name: 'Phosphor' }).click();
    await expect(icons.getByRole('radio', { name: 'Phosphor' })).toHaveAttribute('aria-checked', 'true');
    await expect(page.getByText('Appearance saved.').first()).toBeVisible();

    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-design', 'cozy');
    await expect(icons.getByRole('radio', { name: 'Phosphor' })).toHaveAttribute('aria-checked', 'true');

    await designs.getByRole('radio', { name: 'Classic' }).click();
    await icons.getByRole('radio', { name: 'Lucide' }).click();
    await expect(page.locator('html')).not.toHaveAttribute('data-design', 'cozy');
    await expect(icons.getByRole('radio', { name: 'Lucide' })).toHaveAttribute('aria-checked', 'true');
});
