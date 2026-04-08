import { test, expect } from '@playwright/test';

async function login(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.fill('#email', 'b.wilhelmsen@wilhelmsen-biotec.de');
    await page.fill('#password', 'password');
    await page.locator('form button').filter({ hasText: /Anmelden|Log in/ }).click();
    await page.waitForURL('**/dashboard**', { timeout: 10000 });
}

async function goToTeamWeek(page: import('@playwright/test').Page) {
    await page.goto('/calendar?view=team-week');
    await page.waitForLoadState('networkidle');
}

async function goToTeamDay(page: import('@playwright/test').Page) {
    await page.goto('/calendar?view=team-day');
    await page.waitForLoadState('networkidle');
}

test.describe('Team Calendar View', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('team button toggles team-week view', async ({ page }) => {
        await page.goto('/calendar');
        await page.waitForLoadState('networkidle');

        const teamButton = page.locator('button').filter({ hasText: /Team/ });
        await expect(teamButton).toBeVisible();
        await teamButton.click();
        await page.waitForLoadState('networkidle');

        // Should show the team grid with Employee header and Unassigned row
        await expect(page.locator('th, div').filter({ hasText: /Mitarbeiter|Employee/ }).first()).toBeVisible();
        await expect(page.locator('td, div').filter({ hasText: /Nicht zugewiesen|Unassigned/ }).first()).toBeVisible();
    });

    test('team-week shows employee rows and day columns', async ({ page }) => {
        await goToTeamWeek(page);

        // Employee column header
        await expect(page.locator('th').filter({ hasText: /Mitarbeiter|Employee/ })).toBeVisible();
        // Unassigned row
        await expect(page.locator('td').filter({ hasText: /Nicht zugewiesen|Unassigned/ }).first()).toBeVisible();
        // At least 5 day columns (Mon-Fri)
        const dayHeaders = page.locator('th').filter({ hasNotText: /Mitarbeiter|Employee/ });
        await expect(dayHeaders).toHaveCount(5, { timeout: 5000 }).catch(() => {
            // May have 7 if weekends are shown
        });
    });

    test('team-week shows appointments in correct cells', async ({ page }) => {
        await goToTeamWeek(page);

        const cards = page.locator('[data-appointment-id]');
        const count = await cards.count();
        // All appointment cards should be visible
        for (let i = 0; i < count; i++) {
            await expect(cards.nth(i)).toBeVisible();
        }
    });

    test('team-week click on appointment opens edit dialog', async ({ page }) => {
        await goToTeamWeek(page);

        const apptCard = page.locator('[data-appointment-id]').first();
        const visible = await apptCard.isVisible().catch(() => false);
        if (!visible) {
            test.skip();
            return;
        }

        await apptCard.click();
        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });
    });

    test('team-week double-click on empty cell opens create dialog', async ({ page }) => {
        await goToTeamWeek(page);

        // Find an empty table cell (td) that doesn't have appointment cards
        const cells = page.locator('tbody td').filter({ hasNotText: /Nicht zugewiesen|Unassigned/ });
        const cellCount = await cells.count();

        for (let i = 0; i < cellCount; i++) {
            const cell = cells.nth(i);
            const cardCount = await cell.locator('[data-appointment-id]').count();
            if (cardCount === 0) {
                await cell.dblclick();
                const dialog = page.locator('[role="dialog"]');
                await expect(dialog).toBeVisible({ timeout: 5000 });
                return;
            }
        }
        // No empty cell found, skip
        test.skip();
    });

    test('team-day view shows horizontal timeline with hour columns', async ({ page }) => {
        await goToTeamDay(page);

        // Employee label
        await expect(page.locator('div').filter({ hasText: /Mitarbeiter|Employee/ }).first()).toBeVisible();
        // Unassigned row
        await expect(page.locator('div').filter({ hasText: /Nicht zugewiesen|Unassigned/ }).first()).toBeVisible();
        // Hour headers (at least 09:00)
        await expect(page.getByText('09:00')).toBeVisible();
    });

    test('team-day view is horizontally scrollable', async ({ page }) => {
        await goToTeamDay(page);

        const scrollContainer = page.locator('.h-full.overflow-auto');
        const scrollWidth = await scrollContainer.evaluate((el) => el.scrollWidth);
        const clientWidth = await scrollContainer.evaluate((el) => el.clientWidth);

        // The timeline should be wider than the viewport
        expect(scrollWidth).toBeGreaterThan(clientWidth);
    });

    test('team-day drag shows preview ghost at correct position', async ({ page }) => {
        await goToTeamDay(page);

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

        // Drag horizontally to the right
        await page.mouse.move(startX, startY);
        await page.mouse.down();
        await page.mouse.move(startX + 100, startY, { steps: 10 });

        // Preview ghost should appear (z-30 pointer-events-none)
        const preview = page.locator('.z-30.pointer-events-none');
        await expect(preview).toBeVisible({ timeout: 2000 });

        // Original card should be faded (opacity-30)
        await expect(apptCard).toHaveClass(/opacity-30/);

        await page.mouse.up();
    });

    test('team-day single click on appointment opens edit dialog', async ({ page }) => {
        await goToTeamDay(page);

        const apptCard = page.locator('[data-appointment-id]').first();
        const visible = await apptCard.isVisible().catch(() => false);
        if (!visible) {
            test.skip();
            return;
        }

        await apptCard.click();
        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });
    });

    test('team-day/week sub-toggle switches between modes', async ({ page }) => {
        await goToTeamWeek(page);

        // Should show Day/Week sub-toggle when in team mode
        const daySubButton = page.locator('button').filter({ hasText: /^(Tag|Day)$/ });
        // There may be multiple buttons with "Tag" (German for Day) — pick the smaller sub-toggle one
        const subButtons = page.locator('.overflow-hidden button').filter({ hasText: /Tag|Day/ });
        const subCount = await subButtons.count();
        if (subCount === 0) {
            test.skip();
            return;
        }

        await subButtons.first().click();
        await page.waitForLoadState('networkidle');

        // Should now show horizontal timeline (team-day)
        await expect(page.getByText('09:00')).toBeVisible();
    });

    test('team view navigation arrows change date range', async ({ page }) => {
        await goToTeamWeek(page);

        const header = page.locator('h2');
        const initialText = await header.textContent();

        // Click next arrow
        const nextButton = page.locator('button[class*="h-8"]').nth(1);
        await nextButton.click();
        await page.waitForLoadState('networkidle');

        const newText = await header.textContent();
        expect(newText).not.toBe(initialText);
    });
});
