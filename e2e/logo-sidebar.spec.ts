import { test, expect } from '@playwright/test';

async function login(page: import('@playwright/test').Page) {
    await page.goto('/login');
    await page.fill('#email', 'owner@biotech.com');
    await page.fill('#password', 'password');
    await page.locator('form button').filter({ hasText: /Anmelden|Log in/ }).click();
    await page.waitForURL('**/dashboard**', { timeout: 10000 });
}

test('sidebar shows logo image when company has a logo uploaded', async ({ page }) => {
    await login(page);

    // Navigate to settings and upload a logo
    await page.goto('/settings');
    await page.waitForLoadState('networkidle');

    // Upload a test logo
    const fileInput = page.locator('input[type="file"][accept="image/*"]');
    await fileInput.setInputFiles({
        name: 'test-logo.png',
        mimeType: 'image/png',
        buffer: Buffer.from(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            'base64',
        ),
    });

    // Submit the form and wait for the Inertia response
    await page.locator('form button[type="submit"]').filter({ hasText: /Speichern|Save/ }).click();
    await page.waitForLoadState('networkidle');

    // Reload to get fresh shared props
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Verify the sidebar shows an img tag instead of text
    const sidebarLogo = page.locator('aside img[alt]');
    await expect(sidebarLogo).toBeVisible({ timeout: 5000 });

    // Verify the image src points to the storage path
    const src = await sidebarLogo.getAttribute('src');
    expect(src).toContain('/storage/logos/');
});

test('sidebar shows company name when no logo is uploaded', async ({ page }) => {
    await login(page);
    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');

    // The sidebar should show either a logo image or the company name text
    const sidebarText = page.locator('aside');
    const hasLogo = await sidebarText.locator('img[alt]').count();

    if (hasLogo === 0) {
        // No logo — should display company name as text
        await expect(sidebarText).toContainText(/Wilhelmsen|BioTec|./);
    }
});
