import { test, expect } from '@playwright/test';

// Helper: login as owner
async function login(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.fill('#email', 'owner@biotech.com');
    await page.fill('#password', 'password');
    // The PrimaryButton doesn't explicitly set type="submit", use text match
    await page.locator('form button').filter({ hasText: /Anmelden|Log in/ }).click();
    await page.waitForURL('**/dashboard**', { timeout: 10000 });
}

test.describe('Appointment Dialog - Close behavior', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('can open and close create dialog via X button', async ({ page }) => {
        await page.goto('/calendar');
        await page.waitForSelector('text=New Appointment', { timeout: 10000 }).catch(() => null);
        await page.waitForSelector('text=Neuer Termin', { timeout: 5000 }).catch(() => null);

        // Click the "New Appointment" / "Neuer Termin" button
        const newApptButton = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
        await newApptButton.click();

        // Wait for dialog to appear
        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });

        // Try closing via X button (the close button in top-right corner)
        const closeButton = dialog.locator('button:has(svg.lucide-x), button:has(.sr-only:text("Close"))');
        await closeButton.click();

        // Dialog should be gone
        await expect(dialog).not.toBeVisible({ timeout: 5000 });
    });

    test('can open and close create dialog via Escape key', async ({ page }) => {
        await page.goto('/calendar');
        await page.waitForLoadState('networkidle');

        const newApptButton = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
        await newApptButton.click();

        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });

        // Press Escape to close
        await page.keyboard.press('Escape');

        await expect(dialog).not.toBeVisible({ timeout: 5000 });
    });

    test('can open and close create dialog via overlay click', async ({ page }) => {
        await page.goto('/calendar');
        await page.waitForLoadState('networkidle');

        const newApptButton = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
        await newApptButton.click();

        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });

        // Click the overlay (outside the dialog)
        // The overlay is the black backdrop behind the dialog
        await page.mouse.click(10, 10);

        await expect(dialog).not.toBeVisible({ timeout: 5000 });
    });

    test('can open, fill form, then close dialog', async ({ page }) => {
        await page.goto('/calendar');
        await page.waitForLoadState('networkidle');

        const newApptButton = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
        await newApptButton.click();

        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });

        // Fill in the title field
        const titleInput = dialog.locator('input[type="text"]').first();
        await titleInput.fill('Test Appointment');

        // Now try to close via X button
        const closeButton = dialog.locator('button:has(svg)').filter({ hasText: /Close/ }).or(
            dialog.locator('button.absolute')
        );
        await closeButton.first().click();

        await expect(dialog).not.toBeVisible({ timeout: 5000 });
    });

    test('can reopen dialog after closing', async ({ page }) => {
        await page.goto('/calendar');
        await page.waitForLoadState('networkidle');

        const newApptButton = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });

        // Open
        await newApptButton.click();
        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });

        // Close via Escape
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible({ timeout: 5000 });

        // Re-open
        await newApptButton.click();
        await expect(dialog).toBeVisible({ timeout: 5000 });

        // Close again via Escape
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible({ timeout: 5000 });
    });
});
