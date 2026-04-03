import { test, expect } from '@playwright/test';

async function login(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.fill('#email', 'owner@biotech.com');
    await page.fill('#password', 'password');
    await page.locator('form button').filter({ hasText: /Anmelden|Log in/ }).click();
    await page.waitForURL('**/dashboard**', { timeout: 10000 });
}

async function openCreateDialog(page: import('@playwright/test').Page) {
    await page.goto('/calendar');
    await page.waitForLoadState('networkidle');
    const btn = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
    await btn.click();
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    return dialog;
}

test.describe('Appointment Dialog - Close without changes (no confirm)', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('closes via X button without confirm when clean', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        const closeButton = dialog.locator('button:has(svg.lucide-x), button:has(.sr-only:text("Close"))');
        await closeButton.click();

        await expect(dialog).not.toBeVisible({ timeout: 5000 });
    });

    test('closes via Escape without confirm when clean', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        await page.keyboard.press('Escape');

        await expect(dialog).not.toBeVisible({ timeout: 5000 });
    });

    test('can reopen dialog after clean close', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible({ timeout: 5000 });

        // Re-open
        const btn = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
        await btn.click();
        await expect(dialog).toBeVisible({ timeout: 5000 });

        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible({ timeout: 5000 });
    });
});

test.describe('Appointment Dialog - Confirm discard on close with changes', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    // --- Title change ---

    test('shows confirm when closing via X after title change', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        await dialog.locator('input[type="text"]').first().fill('Changed title');

        dialog.locator('button:has(svg.lucide-x), button:has(.sr-only:text("Close"))').click();

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
    });

    test('shows confirm when closing via Escape after title change', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        await dialog.locator('input[type="text"]').first().fill('Changed title');

        await page.keyboard.press('Escape');

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
    });

    // --- Description change ---

    test('shows confirm when closing after description change', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        await dialog.locator('textarea').fill('Some notes');

        await page.keyboard.press('Escape');

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
    });

    // --- Checklist change ---

    test('shows confirm when closing after adding checklist item', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        const addInput = dialog.locator('input[data-checklist-new-input]');
        await addInput.fill('New task');
        await addInput.press('Enter');

        await page.keyboard.press('Escape');

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
    });

    // --- Client change (only if clients exist) ---

    test('shows confirm when closing after selecting client', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        const clientBtn = dialog.locator('button').filter({ hasText: /Kunde|Client/ }).first();
        await clientBtn.click();
        await page.waitForTimeout(300);

        // Need at least one real client (not just "None")
        const items = page.locator('[role="option"]');
        const count = await items.count();
        if (count <= 1) {
            test.skip(true, 'No clients in test database');
            return;
        }

        await items.nth(1).click();
        await page.waitForTimeout(200);

        await page.keyboard.press('Escape');

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
    });

    // --- Tag change ---

    test('shows confirm when closing after selecting tag', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        const tagBtn = dialog.locator('button').filter({ hasText: 'Tag' }).first();
        await tagBtn.click();
        await page.waitForTimeout(300);

        const items = page.locator('[role="option"]');
        const count = await items.count();
        if (count <= 1) {
            test.skip(true, 'No tags in test database');
            return;
        }

        await items.nth(1).click();
        await page.waitForTimeout(200);

        await page.keyboard.press('Escape');

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
    });

    // --- Date/Time change ---

    test('shows confirm when closing after changing time', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        // Open date/time popover
        const dateBtn = dialog.locator('button').filter({ hasText: /\d{2}:\d{2}/ }).first();
        await dateBtn.click();
        await page.waitForTimeout(300);

        // Change start time
        const timeInputs = page.locator('input[type="time"]');
        await timeInputs.first().fill('10:30');
        await page.waitForTimeout(200);

        // Close the popover first
        await page.keyboard.press('Escape');
        await page.waitForTimeout(200);

        // Now try to close the dialog
        await page.keyboard.press('Escape');

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
    });
});

test.describe('Appointment Dialog - Discard action', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('discard button closes both dialogs', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        await dialog.locator('input[type="text"]').first().fill('Will be discarded');

        await page.keyboard.press('Escape');

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        await alert.locator('button').filter({ hasText: /Discard|Verwerfen/ }).click();

        await expect(alert).not.toBeVisible({ timeout: 5000 });
        await expect(dialog).not.toBeVisible({ timeout: 5000 });
    });

    test('discard via X button closes both dialogs', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        await dialog.locator('textarea').fill('Some description');

        dialog.locator('button:has(svg.lucide-x), button:has(.sr-only:text("Close"))').click();

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        await alert.locator('button').filter({ hasText: /Discard|Verwerfen/ }).click();

        await expect(alert).not.toBeVisible({ timeout: 5000 });
        await expect(dialog).not.toBeVisible({ timeout: 5000 });
    });

    test('form is reset after discard and reopen', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        const titleInput = dialog.locator('input[type="text"]').first();
        await titleInput.fill('Temporary title');

        await page.keyboard.press('Escape');

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
        await alert.locator('button').filter({ hasText: /Discard|Verwerfen/ }).click();

        await expect(dialog).not.toBeVisible({ timeout: 5000 });

        // Reopen — form should be clean
        const btn = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
        await btn.click();
        await expect(dialog).toBeVisible({ timeout: 5000 });

        await expect(titleInput).toHaveValue('');
    });
});

test.describe('Appointment Dialog - Cancel discard (keep editing)', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('cancel keeps dialog open with data preserved', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        const titleInput = dialog.locator('input[type="text"]').first();
        await titleInput.fill('Keep this title');

        await page.keyboard.press('Escape');

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        await alert.locator('button').filter({ hasText: /Cancel|Abbrechen/ }).click();

        await expect(alert).not.toBeVisible({ timeout: 5000 });
        await expect(dialog).toBeVisible();
        await expect(titleInput).toHaveValue('Keep this title');
    });

    test('cancel via X then cancel keeps dialog open', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        await dialog.locator('textarea').fill('Important notes');

        dialog.locator('button:has(svg.lucide-x), button:has(.sr-only:text("Close"))').click();

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        await alert.locator('button').filter({ hasText: /Cancel|Abbrechen/ }).click();

        await expect(alert).not.toBeVisible({ timeout: 5000 });
        await expect(dialog).toBeVisible();
        await expect(dialog.locator('textarea')).toHaveValue('Important notes');
    });

    test('can still submit after cancelling discard', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        const titleInput = dialog.locator('input[type="text"]').first();
        await titleInput.fill('Submit after cancel');

        // Try to close, then cancel
        await page.keyboard.press('Escape');
        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
        await alert.locator('button').filter({ hasText: /Cancel|Abbrechen/ }).click();
        await expect(alert).not.toBeVisible({ timeout: 5000 });

        // Now submit the form
        const submitButton = dialog.locator('button[type="submit"]');
        await submitButton.click();

        await expect(dialog).not.toBeVisible({ timeout: 10000 });
    });
});
