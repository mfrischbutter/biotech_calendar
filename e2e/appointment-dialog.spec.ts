import { test, expect } from '@playwright/test';

async function login(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.fill('#email', 'b.wilhelmsen@wilhelmsen-biotec.de');
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

    // --- Status change ---

    test('shows confirm when closing after selecting status', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        const statusBtn = dialog.locator('button').filter({ hasText: 'Status' }).first();
        await statusBtn.click();
        await page.waitForTimeout(300);

        const items = page.locator('[role="option"]');
        const count = await items.count();
        if (count <= 1) {
            test.skip(true, 'No statuses in test database');
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

test.describe('Appointment Dialog - Edit discard resets form', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
    });

    test('edit dialog resets to original data after discard', async ({ page }) => {
        await page.goto('/calendar');
        await page.waitForLoadState('networkidle');

        // Find and click an existing appointment to open edit dialog
        const appointment = page.locator('[data-appointment-id]').first();
        const hasAppointment = await appointment.count();
        if (hasAppointment === 0) {
            // Create an appointment first
            const newBtn = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
            await newBtn.click();
            const createDialog = page.locator('[role="dialog"]');
            await expect(createDialog).toBeVisible({ timeout: 5000 });
            await createDialog.locator('input[type="text"]').first().fill('Reset Test Appointment');
            await createDialog.locator('button[type="submit"]').click();
            await expect(createDialog).not.toBeVisible({ timeout: 10000 });
            await page.waitForLoadState('networkidle');
        }

        // Click the first appointment on the calendar to open edit
        const apptCard = page.locator('[data-appointment-id]').first();
        if (await apptCard.count() === 0) {
            test.skip(true, 'No appointments visible on calendar');
            return;
        }

        await apptCard.click();
        const editDialog = page.locator('[role="dialog"]');
        await expect(editDialog).toBeVisible({ timeout: 5000 });

        // Read the original title
        const titleInput = editDialog.locator('input[type="text"]').first();
        const originalTitle = await titleInput.inputValue();

        // Change the title
        await titleInput.fill('MODIFIED TITLE');
        await expect(titleInput).toHaveValue('MODIFIED TITLE');

        // Close via Escape → confirm dialog appears
        await page.keyboard.press('Escape');
        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        // Click Discard
        await alert.locator('button').filter({ hasText: /Discard|Verwerfen/ }).click();
        await expect(editDialog).not.toBeVisible({ timeout: 5000 });

        // Reopen the same appointment
        await apptCard.click();
        await expect(editDialog).toBeVisible({ timeout: 5000 });

        // Title should be the original, not "MODIFIED TITLE"
        await expect(titleInput).toHaveValue(originalTitle);
    });

    test('edit dialog resets description after discard', async ({ page }) => {
        await page.goto('/calendar');
        await page.waitForLoadState('networkidle');

        const apptCard = page.locator('[data-appointment-id]').first();
        if (await apptCard.count() === 0) {
            test.skip(true, 'No appointments visible on calendar');
            return;
        }

        await apptCard.click();
        const editDialog = page.locator('[role="dialog"]');
        await expect(editDialog).toBeVisible({ timeout: 5000 });

        // Read original description
        const notesTextarea = editDialog.locator('textarea');
        const originalNotes = await notesTextarea.inputValue();

        // Change description
        await notesTextarea.fill('MODIFIED DESCRIPTION');

        // Close and discard
        await page.keyboard.press('Escape');
        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
        await alert.locator('button').filter({ hasText: /Discard|Verwerfen/ }).click();
        await expect(editDialog).not.toBeVisible({ timeout: 5000 });

        // Reopen
        await apptCard.click();
        await expect(editDialog).toBeVisible({ timeout: 5000 });

        // Description should be original
        await expect(notesTextarea).toHaveValue(originalNotes);
    });

    test('edit dialog after create close does not show discard when clean', async ({ page }) => {
        // Open and immediately close create dialog
        const dialog = await openCreateDialog(page);
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible({ timeout: 5000 });

        // Now open an existing appointment for editing
        await page.waitForLoadState('networkidle');
        const apptCard = page.locator('[data-appointment-id]').first();
        if (await apptCard.count() === 0) {
            test.skip(true, 'No appointments visible on calendar');
            return;
        }

        await apptCard.click();
        const editDialog = page.locator('[role="dialog"]');
        await expect(editDialog).toBeVisible({ timeout: 5000 });

        // Close without making changes — should NOT show discard confirm
        await page.keyboard.press('Escape');

        const alert = page.locator('[role="alertdialog"]');
        // The alert should not appear; the dialog should just close
        await expect(editDialog).not.toBeVisible({ timeout: 5000 });
        await expect(alert).not.toBeVisible();
    });

    test('create dialog resets after discard and reopen', async ({ page }) => {
        const dialog = await openCreateDialog(page);

        const titleInput = dialog.locator('input[type="text"]').first();
        const notesTextarea = dialog.locator('textarea');

        await titleInput.fill('Will be discarded');
        await notesTextarea.fill('These notes will be gone');

        // Add a checklist item
        const addInput = dialog.locator('input[data-checklist-new-input]');
        await addInput.fill('Checklist item');
        await addInput.press('Enter');

        // Close and discard
        await page.keyboard.press('Escape');
        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
        await alert.locator('button').filter({ hasText: /Discard|Verwerfen/ }).click();
        await expect(dialog).not.toBeVisible({ timeout: 5000 });

        // Reopen create dialog
        const btn = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
        await btn.click();
        await expect(dialog).toBeVisible({ timeout: 5000 });

        // Everything should be empty
        await expect(titleInput).toHaveValue('');
        await expect(notesTextarea).toHaveValue('');
        const checklistItems = dialog.locator('input[data-checklist-item-input]');
        await expect(checklistItems).toHaveCount(0);
    });
});

// ---------------------------------------------------------------------------
// Helper: create an appointment via the UI and return its title
// ---------------------------------------------------------------------------
async function createAppointment(
    page: import('@playwright/test').Page,
    title: string,
    description?: string,
) {
    const btn = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
    await btn.click();
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible({ timeout: 5000 });

    await dialog.locator('input[type="text"]').first().fill(title);
    if (description) {
        await dialog.locator('textarea').fill(description);
    }

    await dialog.locator('button[type="submit"]').click();
    await expect(dialog).not.toBeVisible({ timeout: 10000 });
    await page.waitForLoadState('networkidle');
}

// ---------------------------------------------------------------------------
// Helper: open edit dialog for an appointment by its data-appointment-id index
// ---------------------------------------------------------------------------
async function openEditByIndex(page: import('@playwright/test').Page, index: number) {
    const card = page.locator('[data-appointment-id]').nth(index);
    // Use force:true because overlapping appointments can intercept pointer events
    await card.click({ force: true });
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    return dialog;
}

async function openEditById(page: import('@playwright/test').Page, id: string) {
    const card = page.locator(`[data-appointment-id="${id}"]`);
    await card.click({ force: true });
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible({ timeout: 5000 });
    return dialog;
}

function getAppointmentId(page: import('@playwright/test').Page, index: number) {
    return page.locator('[data-appointment-id]').nth(index).getAttribute('data-appointment-id');
}

test.describe('Appointment Dialog - Multi-appointment edit scenarios', () => {
    test.beforeEach(async ({ page }) => {
        await login(page);
        await page.goto('/calendar');
        await page.waitForLoadState('networkidle');

        // Ensure at least 2 appointments exist
        const count = await page.locator('[data-appointment-id]').count();
        if (count < 2) {
            for (let i = count; i < 2; i++) {
                await createAppointment(page, `Test Appointment ${i + 1}`, `Description ${i + 1}`);
            }
        }
    });

    test('opening different appointments shows correct data for each', async ({ page }) => {
        const id0 = await getAppointmentId(page, 0);
        const id1 = await getAppointmentId(page, 1);

        // Open first appointment and read its data
        const dialog1 = await openEditById(page, id0!);
        const title1 = await dialog1.locator('input[type="text"]').first().inputValue();
        const notes1 = await dialog1.locator('textarea').inputValue();
        expect(title1.length).toBeGreaterThan(0);

        // Close cleanly
        await page.keyboard.press('Escape');
        await expect(dialog1).not.toBeVisible({ timeout: 5000 });

        // Open second appointment and read its data
        const dialog2 = await openEditById(page, id1!);
        const title2 = await dialog2.locator('input[type="text"]').first().inputValue();
        expect(title2.length).toBeGreaterThan(0);

        // Close cleanly
        await page.keyboard.press('Escape');
        await expect(dialog2).not.toBeVisible({ timeout: 5000 });

        // Re-open first appointment — should still show original data
        const dialog1b = await openEditById(page, id0!);
        await expect(dialog1b.locator('input[type="text"]').first()).toHaveValue(title1);
        await expect(dialog1b.locator('textarea')).toHaveValue(notes1);

        await page.keyboard.press('Escape');
        await expect(dialog1b).not.toBeVisible({ timeout: 5000 });
    });

    test('edit first, discard, then open second — no stale discard prompt', async ({ page }) => {
        // Open first appointment
        const dialog = await openEditByIndex(page, 0);
        const titleInput = dialog.locator('input[type="text"]').first();

        // Modify title
        await titleInput.fill('MODIFIED FIRST');

        // Close → discard prompt appears
        await page.keyboard.press('Escape');
        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        // Discard
        await alert.locator('button').filter({ hasText: /Discard|Verwerfen/ }).click();
        await expect(dialog).not.toBeVisible({ timeout: 5000 });

        // Open second appointment — should close cleanly without discard prompt
        const dialog2 = await openEditByIndex(page, 1);
        await page.keyboard.press('Escape');

        await expect(dialog2).not.toBeVisible({ timeout: 5000 });
        await expect(alert).not.toBeVisible();
    });

    test('edit first, cancel discard, continue editing, then submit', async ({ page }) => {
        const id0 = await getAppointmentId(page, 0);
        const dialog = await openEditById(page, id0!);
        const titleInput = dialog.locator('input[type="text"]').first();
        const originalTitle = await titleInput.inputValue();

        // Use a unique title that definitely differs from the original
        const uniqueTitle = `E2E-${Date.now()}`;
        await titleInput.fill(uniqueTitle);
        await expect(titleInput).toHaveValue(uniqueTitle);

        // Try to close → discard prompt
        await page.keyboard.press('Escape');
        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });

        // Cancel — go back to editing
        await alert.locator('button').filter({ hasText: /Cancel|Abbrechen/ }).click();
        await expect(alert).not.toBeVisible({ timeout: 5000 });
        await expect(dialog).toBeVisible();

        // Title should still be modified
        await expect(titleInput).toHaveValue(uniqueTitle);

        // Submit
        await dialog.locator('button[type="submit"]').click();
        await expect(dialog).not.toBeVisible({ timeout: 10000 });

        // Re-open — should show saved title (wait for close guard + Inertia re-render)
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(500);
        const dialog2 = await openEditById(page, id0!);
        await expect(dialog2.locator('input[type="text"]').first()).toHaveValue(uniqueTitle);

        // Restore original title for other tests
        await dialog2.locator('input[type="text"]').first().fill(originalTitle);
        await expect(dialog2.locator('input[type="text"]').first()).toHaveValue(originalTitle);
        await dialog2.locator('button[type="submit"]').click();
        await expect(dialog2).not.toBeVisible({ timeout: 10000 });
    });

    test('open edit, close clean, open create, close clean — no cross-contamination', async ({ page }) => {
        // Open edit dialog
        const editDialog = await openEditByIndex(page, 0);
        const editTitle = await editDialog.locator('input[type="text"]').first().inputValue();

        // Close cleanly (no changes)
        await page.keyboard.press('Escape');
        await expect(editDialog).not.toBeVisible({ timeout: 5000 });

        // Open create dialog
        const createBtn = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
        await createBtn.click();
        const createDialog = page.locator('[role="dialog"]');
        await expect(createDialog).toBeVisible({ timeout: 5000 });

        // Create dialog should have empty title (not the edit appointment's title)
        await expect(createDialog.locator('input[type="text"]').first()).toHaveValue('');

        // Close cleanly
        await page.keyboard.press('Escape');
        await expect(createDialog).not.toBeVisible({ timeout: 5000 });

        // Open edit dialog again — should show original data
        const editDialog2 = await openEditByIndex(page, 0);
        await expect(editDialog2.locator('input[type="text"]').first()).toHaveValue(editTitle);

        await page.keyboard.press('Escape');
        await expect(editDialog2).not.toBeVisible({ timeout: 5000 });
    });

    test('modify description, discard, reopen — description is original', async ({ page }) => {
        const dialog = await openEditByIndex(page, 0);
        const textarea = dialog.locator('textarea');
        const originalNotes = await textarea.inputValue();

        await textarea.fill('Temporary notes that will be discarded');

        // Close → discard
        await page.keyboard.press('Escape');
        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
        await alert.locator('button').filter({ hasText: /Discard|Verwerfen/ }).click();
        await expect(dialog).not.toBeVisible({ timeout: 5000 });

        // Reopen
        const dialog2 = await openEditByIndex(page, 0);
        await expect(dialog2.locator('textarea')).toHaveValue(originalNotes);

        await page.keyboard.press('Escape');
        await expect(dialog2).not.toBeVisible({ timeout: 5000 });
    });

    test('open and close multiple appointments in sequence without changes', async ({ page }) => {
        const alert = page.locator('[role="alertdialog"]');

        // Open/close first
        const d1 = await openEditByIndex(page, 0);
        await page.keyboard.press('Escape');
        await expect(d1).not.toBeVisible({ timeout: 5000 });
        await expect(alert).not.toBeVisible();

        // Open/close second
        const d2 = await openEditByIndex(page, 1);
        await page.keyboard.press('Escape');
        await expect(d2).not.toBeVisible({ timeout: 5000 });
        await expect(alert).not.toBeVisible();

        // Open/close first again
        const d3 = await openEditByIndex(page, 0);
        await page.keyboard.press('Escape');
        await expect(d3).not.toBeVisible({ timeout: 5000 });
        await expect(alert).not.toBeVisible();

        // Open/close second again
        const d4 = await openEditByIndex(page, 1);
        await page.keyboard.press('Escape');
        await expect(d4).not.toBeVisible({ timeout: 5000 });
        await expect(alert).not.toBeVisible();
    });

    test('create then immediately edit — create form clean, edit form shows data', async ({ page }) => {
        // Open create dialog and close immediately
        const createBtn = page.locator('button').filter({ hasText: /New Appointment|Neuer Termin/ });
        await createBtn.click();
        const dialog = page.locator('[role="dialog"]');
        await expect(dialog).toBeVisible({ timeout: 5000 });
        await page.keyboard.press('Escape');
        await expect(dialog).not.toBeVisible({ timeout: 5000 });

        // Open edit — should show data and close without discard
        const editDialog = await openEditByIndex(page, 0);
        const title = await editDialog.locator('input[type="text"]').first().inputValue();
        expect(title.length).toBeGreaterThan(0);

        await page.keyboard.press('Escape');
        await expect(editDialog).not.toBeVisible({ timeout: 5000 });
        await expect(page.locator('[role="alertdialog"]')).not.toBeVisible();
    });

    test('modify edit, discard, open different appointment — shows correct data', async ({ page }) => {
        // Open first and modify
        const dialog = await openEditByIndex(page, 0);
        await dialog.locator('input[type="text"]').first().fill('WRONG TITLE');

        // Discard
        await page.keyboard.press('Escape');
        const alert = page.locator('[role="alertdialog"]');
        await expect(alert).toBeVisible({ timeout: 5000 });
        await alert.locator('button').filter({ hasText: /Discard|Verwerfen/ }).click();
        await expect(dialog).not.toBeVisible({ timeout: 5000 });

        // Open second appointment — should show ITS title, not "WRONG TITLE"
        const dialog2 = await openEditByIndex(page, 1);
        const secondTitle = await dialog2.locator('input[type="text"]').first().inputValue();
        expect(secondTitle).not.toBe('WRONG TITLE');
        expect(secondTitle.length).toBeGreaterThan(0);

        await page.keyboard.press('Escape');
        await expect(dialog2).not.toBeVisible({ timeout: 5000 });
    });
});
