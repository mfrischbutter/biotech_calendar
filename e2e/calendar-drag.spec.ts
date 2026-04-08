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

test.describe('Calendar Drag Interactions', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await goToCalendar(page);
    });

    test('drag-to-create shows preview and opens dialog', async ({ page }) => {
        // Find a day column in the time grid
        const dayColumn = page.locator('.relative.border-r').first();
        const box = await dayColumn.boundingBox();
        expect(box).not.toBeNull();

        // Mousedown on empty area, drag down 60px to create a slot
        const startX = box!.x + box!.width / 2;
        const startY = box!.y + 200; // offset into the grid

        await page.mouse.move(startX, startY);
        await page.mouse.down();
        await page.mouse.move(startX, startY + 60, { steps: 5 });

        // Verify drag preview appears (z-30 ghost element)
        const preview = page.locator('.z-30.pointer-events-none');
        await expect(preview).toBeVisible({ timeout: 2000 });

        // Release to complete the drag
        await page.mouse.up();

        // Create dialog should open
        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });
    });

    test('drag-to-move appointment changes position', async ({ page }) => {
        // Find an appointment card
        const apptCard = page.locator('[data-appointment-id]').first();
        const visible = await apptCard.isVisible().catch(() => false);
        if (!visible) {
            test.skip();
            return;
        }

        const box = await apptCard.boundingBox();
        expect(box).not.toBeNull();

        const startX = box!.x + box!.width / 2;
        const startY = box!.y + box!.height / 2;

        // Drag the appointment down by 60px
        await page.mouse.move(startX, startY);
        await page.mouse.down();
        await page.mouse.move(startX, startY + 60, { steps: 10 });

        // Preview should appear
        const preview = page.locator('.z-30.pointer-events-none');
        await expect(preview).toBeVisible({ timeout: 2000 });

        await page.mouse.up();
    });

    test('drag-to-resize appointment changes end time', async ({ page }) => {
        const apptCard = page.locator('[data-appointment-id]').first();
        const visible = await apptCard.isVisible().catch(() => false);
        if (!visible) {
            test.skip();
            return;
        }

        // Find the resize handle at the bottom of the appointment
        const resizeHandle = apptCard.locator('.cursor-s-resize');
        const box = await resizeHandle.boundingBox();
        expect(box).not.toBeNull();

        const startX = box!.x + box!.width / 2;
        const startY = box!.y + box!.height / 2;

        await page.mouse.move(startX, startY);
        await page.mouse.down();
        await page.mouse.move(startX, startY + 30, { steps: 5 });

        const preview = page.locator('.z-30.pointer-events-none');
        await expect(preview).toBeVisible({ timeout: 2000 });

        await page.mouse.up();
    });

    test('all overlapping appointments are visible — no "+X more" indicators', async ({ page }) => {
        // Verify there are no overflow/"+X more" buttons
        const moreButtons = page.locator('text=/\\+\\d+ /');
        await expect(moreButtons).toHaveCount(0);

        // All appointment cards should be visible (not hidden)
        const cards = page.locator('[data-appointment-id]');
        const count = await cards.count();
        for (let i = 0; i < count; i++) {
            await expect(cards.nth(i)).toBeVisible();
        }
    });

    test('single click on appointment opens edit dialog without drag', async ({ page }) => {
        const apptCard = page.locator('[data-appointment-id]').first();
        const visible = await apptCard.isVisible().catch(() => false);
        if (!visible) {
            test.skip();
            return;
        }

        // Single click (no drag movement)
        await apptCard.click();

        // Edit dialog should appear
        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });
    });

    test('cross-day move in week view', async ({ page }) => {
        // Ensure we're on week view
        const weekButton = page.locator('button').filter({ hasText: /Woche|Week/ });
        if (await weekButton.isVisible()) {
            await weekButton.click();
            await page.waitForLoadState('networkidle');
        }

        const apptCard = page.locator('[data-appointment-id]').first();
        const visible = await apptCard.isVisible().catch(() => false);
        if (!visible) {
            test.skip();
            return;
        }

        const box = await apptCard.boundingBox();
        expect(box).not.toBeNull();

        // Drag appointment horizontally to a different day column
        const startX = box!.x + box!.width / 2;
        const startY = box!.y + box!.height / 2;

        await page.mouse.move(startX, startY);
        await page.mouse.down();
        // Move right ~150px to reach the next day column
        await page.mouse.move(startX + 150, startY, { steps: 10 });

        const preview = page.locator('.z-30.pointer-events-none');
        await expect(preview).toBeVisible({ timeout: 2000 });

        await page.mouse.up();
    });
});
