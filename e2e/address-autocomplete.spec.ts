import { test, expect } from '@playwright/test';

async function login(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.fill('#email', 'b.wilhelmsen@wilhelmsen-biotec.de');
    await page.fill('#password', 'password');
    await page.locator('form button').filter({ hasText: /Anmelden|Log in/ }).click();
    await page.waitForURL('**/dashboard**', { timeout: 10000 });
}

async function typeAddress(page: import('@playwright/test').Page, input: import('@playwright/test').Locator, address: string) {
    await input.click();
    await input.clear();
    await page.keyboard.type(address, { delay: 30 });
}

test.describe('Address Autocomplete', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test.describe('Client Form', () => {
        async function openClientDrawer(page: import('@playwright/test').Page) {
            await page.goto('/clients');
            await page.waitForLoadState('networkidle');
            const newClientButton = page.locator('button').filter({ hasText: /New Client|Neuer Kunde/ });
            await newClientButton.click();
            const drawer = page.locator('[role="dialog"]');
            await expect(drawer).toBeVisible({ timeout: 5000 });
            return drawer;
        }

        test('street field renders as autocomplete component', async ({ page }) => {
            const drawer = await openClientDrawer(page);
            const autocomplete = drawer.locator('[data-testid="address-autocomplete"]');
            await expect(autocomplete).toBeVisible();
        });

        test('typing a German address shows suggestions dropdown', async ({ page }) => {
            const drawer = await openClientDrawer(page);
            const autocomplete = drawer.locator('[data-testid="address-autocomplete"]');
            const input = autocomplete.locator('input');

            await typeAddress(page, input, 'Jungfernstieg 1 Hamburg');

            const suggestionsDropdown = drawer.locator('[data-testid="address-suggestions"]');
            await expect(suggestionsDropdown).toBeVisible({ timeout: 10000 });

            const items = suggestionsDropdown.locator('li');
            await expect(items.first()).toBeVisible({ timeout: 10000 });
            expect(await items.count()).toBeGreaterThan(0);
        });

        test('selecting a suggestion fills zip and city', async ({ page }) => {
            const drawer = await openClientDrawer(page);
            const autocomplete = drawer.locator('[data-testid="address-autocomplete"]');
            const input = autocomplete.locator('input');

            await typeAddress(page, input, 'Jungfernstieg 1 Hamburg');

            const suggestionsDropdown = drawer.locator('[data-testid="address-suggestions"]');
            const firstItem = suggestionsDropdown.locator('li').first();
            await expect(firstItem).toBeVisible({ timeout: 10000 });
            await firstItem.click();

            await expect(suggestionsDropdown).not.toBeVisible({ timeout: 3000 });

            const streetInput = drawer.locator('#client-street');
            await expect(streetInput).not.toHaveValue('');

            const zipInput = drawer.locator('#client-zip');
            const cityInput = drawer.locator('#client-city');

            await expect(zipInput).not.toHaveValue('', { timeout: 5000 });
            await expect(cityInput).not.toHaveValue('', { timeout: 5000 });
        });

        test('short input does not trigger suggestions', async ({ page }) => {
            const drawer = await openClientDrawer(page);
            const autocomplete = drawer.locator('[data-testid="address-autocomplete"]');
            const input = autocomplete.locator('input');

            await typeAddress(page, input, 'Be');
            await page.waitForTimeout(500);

            const suggestionsDropdown = drawer.locator('[data-testid="address-suggestions"]');
            await expect(suggestionsDropdown).not.toBeVisible();
        });
    });

    test.describe('Appointment Form', () => {
        async function openCreateDialog(page: import('@playwright/test').Page) {
            await page.goto('/calendar');
            await page.waitForLoadState('networkidle');
            const newApptButton = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
            await newApptButton.click();
            const dialog = page.locator('[role="dialog"]');
            await expect(dialog).toBeVisible({ timeout: 5000 });
            return dialog;
        }

        test('custom address section has autocomplete on street field', async ({ page }) => {
            const dialog = await openCreateDialog(page);

            const customAddrButton = dialog.locator('button').filter({ hasText: /Custom address|Abweichende Adresse/ });
            await customAddrButton.click();

            const autocomplete = dialog.locator('[data-testid="address-autocomplete"]');
            await expect(autocomplete).toBeVisible({ timeout: 3000 });
        });

        test('typing in appointment address shows suggestions', async ({ page }) => {
            const dialog = await openCreateDialog(page);

            const customAddrButton = dialog.locator('button').filter({ hasText: /Custom address|Abweichende Adresse/ });
            await customAddrButton.click();

            const autocomplete = dialog.locator('[data-testid="address-autocomplete"]');
            const input = autocomplete.locator('input');

            await typeAddress(page, input, 'Mönckebergstraße 7 Hamburg');

            const suggestionsDropdown = dialog.locator('[data-testid="address-suggestions"]');
            await expect(suggestionsDropdown).toBeVisible({ timeout: 10000 });

            const items = suggestionsDropdown.locator('li');
            await expect(items.first()).toBeVisible({ timeout: 10000 });
        });
    });

    test.describe('Company Profile Form', () => {
        test('settings page street field has autocomplete', async ({ page }) => {
            await page.goto('/settings');
            await page.waitForLoadState('networkidle');

            const autocomplete = page.locator('[data-testid="address-autocomplete"]');
            await expect(autocomplete).toBeVisible();
        });

        test('typing in company address shows suggestions and fills fields', async ({ page }) => {
            await page.goto('/settings');
            await page.waitForLoadState('networkidle');

            const autocomplete = page.locator('[data-testid="address-autocomplete"]');
            const input = autocomplete.locator('input');

            await typeAddress(page, input, 'Reeperbahn 1 Hamburg');

            const suggestionsDropdown = page.locator('[data-testid="address-suggestions"]');
            const firstItem = suggestionsDropdown.locator('li').first();
            await expect(firstItem).toBeVisible({ timeout: 10000 });
            await firstItem.click();

            const zipInput = page.locator('#company-zip');
            const cityInput = page.locator('#company-city');

            await expect(zipInput).not.toHaveValue('', { timeout: 5000 });
            await expect(cityInput).not.toHaveValue('', { timeout: 5000 });
        });
    });
});
