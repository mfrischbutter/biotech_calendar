import { test, expect } from '@playwright/test';

async function login(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.fill('#email', 'b.wilhelmsen@wilhelmsen-biotec.de');
    await page.fill('#password', 'password');
    await page.locator('form button').filter({ hasText: /Anmelden|Log in/ }).click();
    await page.waitForURL('**/dashboard**', { timeout: 10000 });
}

async function goToCalendar(page: import('@playwright/test').Page) {
    await page.goto('/calendar');
    await page.waitForLoadState('networkidle');
}

async function openEditByIndex(page: import('@playwright/test').Page, index: number) {
    const card = page.locator('[data-appointment-id]').nth(index);
    await card.click({ force: true });
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    return dialog;
}

function deleteButton(dialog: import('@playwright/test').Locator) {
    return dialog.locator('[data-testid="delete-appointment"]');
}

test.describe('Appointment Delete - Single Appointment', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await goToCalendar(page);
    });

    test('delete button is visible in edit dialog', async ({ page }) => {
        const cards = page.locator('[data-appointment-id]');
        if ((await cards.count()) === 0) { test.skip(); return; }

        const dialog = await openEditByIndex(page, 0);
        await expect(deleteButton(dialog)).toBeVisible();
    });

    test('delete button is NOT visible in create dialog', async ({ page }) => {
        const btn = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
        await btn.click();
        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });

        await expect(deleteButton(dialog)).not.toBeVisible();
    });

    test('clicking delete shows confirmation dialog', async ({ page }) => {
        const cards = page.locator('[data-appointment-id]');
        if ((await cards.count()) === 0) { test.skip(); return; }

        const dialog = await openEditByIndex(page, 0);
        await deleteButton(dialog).click();

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
    });

    test('cancel in confirmation dialog keeps appointment', async ({ page }) => {
        const cards = page.locator('[data-appointment-id]');
        if ((await cards.count()) === 0) { test.skip(); return; }

        const dialog = await openEditByIndex(page, 0);
        await deleteButton(dialog).click();

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        const cancelBtn = alert.locator('button').filter({ hasText: /Cancel|Abbrechen/ });
        await cancelBtn.click();
        await expect(alert).not.toBeVisible({ timeout: 3000 });

        // Edit dialog should still be open
        await expect(dialog).toBeVisible();
    });

    test('confirming delete removes appointment and closes dialog', async ({ page }) => {
        const cards = page.locator('[data-appointment-id]');
        if ((await cards.count()) === 0) { test.skip(); return; }

        const countBefore = await cards.count();
        const dialog = await openEditByIndex(page, 0);
        await deleteButton(dialog).click();

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        // For non-recurring: "Delete/Löschen"; for recurring: "Delete This Only/Nur diesen Termin löschen"
        const confirmBtn = alert.locator('button').filter({
            hasText: /^(Delete|Löschen|Delete This Only|Nur diesen Termin löschen)$/,
        });
        await confirmBtn.first().click();

        await page.waitForLoadState('networkidle');
        await expect(page.locator('[role="dialog"]')).not.toBeVisible({ timeout: 5000 });

        const countAfter = await page.locator('[data-appointment-id]').count();
        expect(countAfter).toBeLessThan(countBefore);
    });

    test('clicking outside confirmation dialog closes it', async ({ page }) => {
        const cards = page.locator('[data-appointment-id]');
        if ((await cards.count()) === 0) { test.skip(); return; }

        const dialog = await openEditByIndex(page, 0);
        await deleteButton(dialog).click();

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        // Click outside the alert dialog overlay
        await page.mouse.click(10, 10);
        await expect(alert).not.toBeVisible({ timeout: 3000 });
    });
});

test.describe('Appointment Delete - Recurring Series', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    async function findRecurringAppointment(page: import('@playwright/test').Page) {
        // Check current week and next week for recurring appointments
        for (const weekOffset of [0, 1]) {
            if (weekOffset === 0) {
                await page.goto('/calendar?view=week');
            } else {
                await page.locator('button[class*="h-8"]').nth(1).click();
            }
            await page.waitForLoadState('networkidle');

            // Re-query cards after each navigation
            const count = await page.locator('[data-appointment-id]').count();

            for (let i = 0; i < count; i++) {
                const card = page.locator('[data-appointment-id]').nth(i);
                await card.click({ force: true });

                const dialog = page.locator('[role="dialog"]');
                await expect(dialog).toBeVisible({ timeout: 5000 });

                const moreBtn = dialog.locator('button.bg-orange-50');
                const isRecurring = (await moreBtn.count()) > 0;

                if (isRecurring) {
                    return dialog;
                }

                await page.keyboard.press('Escape');
                await expect(dialog).not.toBeVisible({ timeout: 3000 });
            }
        }

        return null;
    }

    async function findNonRecurringAppointment(page: import('@playwright/test').Page) {
        await page.goto('/calendar?view=week');
        await page.waitForLoadState('networkidle');

        const cards = page.locator('[data-appointment-id]');
        const count = await cards.count();

        for (let i = 0; i < count; i++) {
            const card = cards.nth(i);
            await card.click({ force: true });

            const dialog = page.locator('[role="dialog"]');
            await expect(dialog).toBeVisible({ timeout: 5000 });

            const moreBtn = dialog.locator('button.bg-orange-50');
            const isRecurring = (await moreBtn.count()) > 0;

            if (!isRecurring) {
                return dialog;
            }

            await page.keyboard.press('Escape');
            await expect(dialog).not.toBeVisible({ timeout: 3000 });
        }

        return null;
    }

    test('recurring appointment shows three delete options', async ({ page }) => {
        const dialog = await findRecurringAppointment(page);
        if (!dialog) { test.skip(); return; }

        await deleteButton(dialog).click();

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        // Should show series-specific description
        await expect(alert.locator('p').filter({ hasText: /Serie|series/i })).toBeVisible();

        // Should have three action buttons
        await expect(alert.locator('button').filter({
            hasText: /Delete This Only|Nur diesen Termin löschen/,
        })).toBeVisible();
        await expect(alert.locator('button').filter({
            hasText: /Delete All Future|alle zukünftigen/,
        })).toBeVisible();
        await expect(alert.locator('button').filter({
            hasText: /Delete Entire Series|Gesamte Serie/,
        })).toBeVisible();
    });

    test('non-recurring appointment shows single delete button only', async ({ page }) => {
        const dialog = await findNonRecurringAppointment(page);
        if (!dialog) { test.skip(); return; }

        await deleteButton(dialog).click();

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        // Should NOT show series options
        await expect(alert.locator('button').filter({
            hasText: /Delete All Future|alle zukünftigen/,
        })).not.toBeVisible();
        await expect(alert.locator('button').filter({
            hasText: /Delete Entire Series|Gesamte Serie/,
        })).not.toBeVisible();

        // Should show single delete button
        await expect(alert.locator('button').filter({
            hasText: /^(Delete|Löschen)$/,
        })).toBeVisible();
    });

    test('delete this only removes one appointment from series', async ({ page }) => {
        const dialog = await findRecurringAppointment(page);
        if (!dialog) { test.skip(); return; }

        const countBefore = await page.locator('[data-appointment-id]').count();

        await deleteButton(dialog).click();

        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        await alert.locator('button').filter({
            hasText: /Delete This Only|Nur diesen Termin löschen/,
        }).click();

        await page.waitForLoadState('networkidle');
        await expect(page.locator('[role="dialog"]')).not.toBeVisible({ timeout: 5000 });

        const countAfter = await page.locator('[data-appointment-id]').count();
        expect(countAfter).toBe(countBefore - 1);
    });
});
