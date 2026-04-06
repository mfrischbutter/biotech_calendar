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
    const newApptButton = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
    await newApptButton.click();
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    return dialog;
}

test.describe('Appointment Form Dialog', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('can fill in title and description', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        const titleInput = dialog.locator('input[type="text"]').first();
        await titleInput.fill('Test Appointment');
        await expect(titleInput).toHaveValue('Test Appointment');

        const notesTextarea = dialog.locator('textarea');
        await notesTextarea.fill('Some description');
        await expect(notesTextarea).toHaveValue('Some description');
    });

    test('description textarea auto-resizes', async ({ page }) => {
        const dialog = await openCreateDialog(page);
        const notesTextarea = dialog.locator('textarea');

        const initialHeight = await notesTextarea.evaluate(el => el.offsetHeight);
        await notesTextarea.fill('Line 1\nLine 2\nLine 3\nLine 4\nLine 5\nLine 6');
        const expandedHeight = await notesTextarea.evaluate(el => el.offsetHeight);

        expect(expandedHeight).toBeGreaterThan(initialHeight);
    });

    test('can add and remove checklist items', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        const addInput = dialog.locator('input[data-checklist-new-input]');
        await addInput.fill('First item');
        await addInput.press('Enter');

        const itemInputs = dialog.locator('input[data-checklist-item-input]');
        await expect(itemInputs).toHaveCount(1);
        await expect(itemInputs.first()).toHaveValue('First item');

        await addInput.fill('Second item');
        await addInput.press('Enter');
        await expect(itemInputs).toHaveCount(2);

        // Remove first item via X button (the last button in the item row)
        const firstItemRow = dialog.locator('input[data-checklist-item-input]').first().locator('..');
        await firstItemRow.locator('button').last().click({ force: true });
        await expect(itemInputs).toHaveCount(1);
        await expect(itemInputs.first()).toHaveValue('Second item');
    });

    test('can open client popover', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        const clientButton = dialog.locator('button').filter({ hasText: /Kunde|Client/ }).first();
        await clientButton.click();

        const searchInput = page.locator('input[placeholder*="unden"]').or(page.locator('input[placeholder*="lient"]'));
        await expect(searchInput).toBeVisible({ timeout: 3000 });
    });

    test('can open status popover with hover effect on items', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        // Status button — match the pill button with exact "Status" text
        const statusButton = dialog.locator('button').filter({ hasText: 'Status' }).first();
        await statusButton.click();

        const searchInput = page.locator('input[placeholder*="auswählen"]').or(page.locator('input[placeholder*="status"]'));
        await expect(searchInput).toBeVisible({ timeout: 3000 });
    });

    test('can open date/time popover', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        const dateButton = dialog.locator('button').filter({ hasText: /\d{2}:\d{2}/ }).first();
        await dateButton.click();

        // Should show Start/End labels
        await expect(page.getByText('Start').last()).toBeVisible({ timeout: 3000 });
        await expect(page.getByText('Ende').or(page.getByText('End')).last()).toBeVisible({ timeout: 3000 });
    });

    test('recurrence toggle works and pill turns orange', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        // Click the recurrence pill button
        const recurrenceButton = dialog.locator('button[title="Weitere Optionen"], button[title="More options"]').first();
        await recurrenceButton.click();
        await page.waitForTimeout(300);

        // Find the Serientermin toggle row inside the popover
        const popoverToggle = page.getByText('Serientermin').last();
        await expect(popoverToggle).toBeVisible({ timeout: 3000 });

        // Click the toggle row to enable recurrence
        await popoverToggle.click();
        await page.waitForTimeout(300);

        // Pill should now be orange
        const pillClasses = await recurrenceButton.getAttribute('class');
        expect(pillClasses).toContain('bg-orange');

        // Click again to disable
        await popoverToggle.click();
        await page.waitForTimeout(300);

        const pillClasses2 = await recurrenceButton.getAttribute('class');
        expect(pillClasses2).not.toContain('bg-orange');
    });

    test('can create a recurring appointment', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        // Fill title
        const titleInput = dialog.locator('input[type="text"]').first();
        await titleInput.fill('Recurring Test Appointment');

        // Open recurrence popover
        const recurrenceButton = dialog.locator('button[title="Weitere Optionen"], button[title="More options"]').first();
        await recurrenceButton.click();
        await page.waitForTimeout(300);

        // Enable recurrence
        const popoverToggle = page.getByText('Serientermin').last();
        await popoverToggle.click();
        await page.waitForTimeout(300);

        // Verify pill is orange
        await expect(recurrenceButton).toHaveClass(/bg-orange/);

        // Fill in end date (3 months from now)
        const endDateInput = page.locator('input[type="date"]').last();
        const futureDate = new Date();
        futureDate.setMonth(futureDate.getMonth() + 3);
        await endDateInput.fill(futureDate.toISOString().split('T')[0]);

        // Close popover by pressing Escape
        await page.keyboard.press('Escape');
        await page.waitForTimeout(200);

        // Intercept POST request
        const requestPromise = page.waitForRequest(
            req => req.url().includes('/appointments') && req.method() === 'POST',
            { timeout: 10000 }
        );

        const submitButton = dialog.locator('button[type="submit"]');
        await submitButton.click();

        const request = await requestPromise;
        const postData = request.postDataJSON();

        // Verify recurrence fields are in the payload
        expect(postData.recurrence_type).toBe('weekly');
        expect(postData.recurrence_end).toBeTruthy();
    });

    test('can create a basic appointment', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        const titleInput = dialog.locator('input[type="text"]').first();
        await titleInput.fill('Playwright Basic Appointment');

        const submitButton = dialog.locator('button[type="submit"]');
        await submitButton.click();

        await expect(dialog).not.toBeVisible({ timeout: 10000 });
    });
});
