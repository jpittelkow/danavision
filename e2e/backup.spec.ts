import { test, expect } from '@playwright/test';

// Helper to login as admin
async function loginAsAdmin(page: any) {
  await page.goto('/login');
  await page.getByLabel(/email/i).fill('admin@example.com');
  await page.getByLabel(/password/i).fill('adminpassword');
  await page.getByRole('button', { name: /sign in/i }).click();
  await page.waitForURL(/\/dashboard/);
}

test.describe('Backup & Restore', () => {
  test.describe('Admin Access', () => {
    test.skip('displays backup page for admin users', async ({ page }) => {
      await loginAsAdmin(page);
      await page.goto('/admin/backup');

      await expect(page.getByRole('heading', { name: /backup/i })).toBeVisible();
      await expect(page.getByRole('button', { name: /create backup/i })).toBeVisible();
    });

    test.skip('can create a backup', async ({ page }) => {
      await loginAsAdmin(page);
      await page.goto('/admin/backup');

      await page.getByRole('button', { name: /create backup/i }).click();

      // Wait for backup creation
      await expect(page.getByText(/backup created|success/i)).toBeVisible({ timeout: 30000 });
    });

    test.skip('displays backup list', async ({ page }) => {
      await loginAsAdmin(page);
      await page.goto('/admin/backup');

      // Check for backup list section
      await expect(page.getByText(/available backups/i)).toBeVisible();
    });
  });

  test.describe('Non-Admin Access', () => {
    test('redirects non-admin users', async ({ page }) => {
      // Login as regular user
      await page.goto('/login');
      await page.getByLabel(/email/i).fill('user@example.com');
      await page.getByLabel(/password/i).fill('password123');
      await page.getByRole('button', { name: /sign in/i }).click();

      // Try to access backup page
      await page.goto('/admin/backup');

      // Should be redirected or show access denied
      const isRedirected = page.url().includes('/login') || page.url().includes('/dashboard');
      const hasAccessDenied = await page.getByText(/access denied|unauthorized|forbidden/i).isVisible().catch(() => false);

      expect(isRedirected || hasAccessDenied).toBe(true);
    });
  });
});
