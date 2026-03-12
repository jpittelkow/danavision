import { test, expect } from '@playwright/test';

// Helper to login as admin user
async function loginAsAdmin(page: any) {
  await page.goto('/login');
  await page.getByLabel(/email/i).fill('admin@example.com');
  await page.getByLabel(/password/i).fill('adminpassword');
  await page.getByRole('button', { name: /sign in/i }).click();
  await page.waitForURL(/\/dashboard/);
}

test.describe('Settings', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test.describe('Profile Settings', () => {
    test('displays profile form when logged in', async ({ page }) => {
      await page.goto('/settings/profile');

      await expect(page.getByRole('heading', { name: /profile/i })).toBeVisible();
      await expect(page.getByLabel(/name/i)).toBeVisible();
      await expect(page.getByLabel(/email/i)).toBeVisible();
    });

    test('can update profile information', async ({ page }) => {
      await page.goto('/settings/profile');

      const nameInput = page.getByLabel(/name/i);
      await nameInput.clear();
      await nameInput.fill('Updated Test Name');

      const saveButton = page.getByRole('button', { name: /save/i });
      await saveButton.click();

      // Wait for success message
      await expect(page.getByText(/success|updated|saved/i)).toBeVisible({ timeout: 10000 });
    });
  });

  test.describe('Security Settings', () => {
    test('displays security options when logged in', async ({ page }) => {
      await page.goto('/settings/security');

      await expect(page.getByRole('heading', { name: /security/i })).toBeVisible();
      // Check for 2FA section
      await expect(page.getByText(/two-factor authentication/i)).toBeVisible();
    });

    test('displays password change form', async ({ page }) => {
      await page.goto('/settings/security');

      // Look for password change section
      await expect(page.getByText(/change password|update password/i)).toBeVisible();
      await expect(page.getByLabel(/current password|old password/i)).toBeVisible();
      await expect(page.getByLabel(/new password/i)).toBeVisible();
    });

    test('displays 2FA section', async ({ page }) => {
      await page.goto('/settings/security');

      await expect(page.getByText(/two-factor authentication|2fa/i)).toBeVisible();
    });
  });

  test.describe('Notification Settings', () => {
    test('displays notification preferences when logged in', async ({ page }) => {
      await page.goto('/settings/notifications');

      await expect(page.getByRole('heading', { name: /notifications/i })).toBeVisible();
    });

    test('displays notification channels', async ({ page }) => {
      await page.goto('/settings/notifications');

      // Wait for channels to load
      await page.waitForTimeout(1000);

      // Check for at least one channel card
      const channelCards = page.locator('[class*="card"]').filter({ hasText: /email|telegram|discord|slack/i });
      await expect(channelCards.first()).toBeVisible({ timeout: 5000 });
    });

    test('can toggle notification channel', async ({ page }) => {
      await page.goto('/settings/notifications');

      // Wait for channels to load
      await page.waitForTimeout(1000);

      // Find first channel toggle switch
      const toggle = page.locator('button[role="switch"]').first();
      if (await toggle.isVisible()) {
        const initialState = await toggle.getAttribute('aria-checked');
        await toggle.click();

        // Wait for update
        await page.waitForTimeout(500);

        // Verify toggle state changed or success message appears
        const newState = await toggle.getAttribute('aria-checked');
        expect(newState).not.toBe(initialState);
      }
    });
  });

  test.describe('AI Settings', () => {
    test('displays AI configuration when logged in', async ({ page }) => {
      await page.goto('/settings/ai');

      await expect(page.getByRole('heading', { name: /ai|llm/i })).toBeVisible();
    });

    test('displays orchestration mode selector', async ({ page }) => {
      await page.goto('/settings/ai');

      // Look for mode tabs or selector
      await expect(
        page.getByText(/single|aggregation|council|orchestration mode/i)
      ).toBeVisible({ timeout: 5000 });
    });

    test('displays providers section', async ({ page }) => {
      await page.goto('/settings/ai');

      // Look for providers section
      await expect(
        page.getByText(/providers|ai providers/i)
      ).toBeVisible({ timeout: 5000 });
    });

    test('can open add provider dialog', async ({ page }) => {
      await page.goto('/settings/ai');

      // Wait for page to load
      await page.waitForTimeout(1000);

      // Look for add provider button
      const addButton = page.getByRole('button', { name: /add provider|new provider/i });
      if (await addButton.isVisible()) {
        await addButton.click();

        // Check if dialog opens
        await expect(
          page.getByRole('dialog').or(page.getByText(/add ai provider|configure provider/i))
        ).toBeVisible({ timeout: 2000 });
      }
    });
  });

  test.describe('Non-Admin Access', () => {
    test('redirects non-admin users from settings', async ({ page }) => {
      // Login as regular user
      await page.goto('/login');
      await page.getByLabel(/email/i).fill('user@example.com');
      await page.getByLabel(/password/i).fill('password123');
      await page.getByRole('button', { name: /sign in/i }).click();
      await page.waitForURL(/\/dashboard/);

      // Try to access settings page
      await page.goto('/settings/profile');

      // Should be redirected or show access denied
      const isRedirected = page.url().includes('/login') || page.url().includes('/dashboard');
      const hasAccessDenied = await page.getByText(/access denied|unauthorized|forbidden/i).isVisible().catch(() => false);

      expect(isRedirected || hasAccessDenied).toBe(true);
    });
  });
});
