import { test as setup, expect } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(__dirname, '.auth/user.json');

/**
 * Authentication Setup
 * 
 * This setup runs before all tests to authenticate a user.
 * The auth state is saved and reused across all tests.
 */
setup('authenticate', async ({ page }) => {
  // Navigate to login page
  await page.goto('/login');

  // Fill in login credentials
  // Note: These should be test credentials - create a test user in your seeder
  await page.fill('input[name="email"], input[type="email"]', process.env.TEST_USER_EMAIL || 'test@example.com');
  await page.fill('input[name="password"], input[type="password"]', process.env.TEST_USER_PASSWORD || 'password');

  // Submit the form
  await page.click('button[type="submit"]');

  // Wait for navigation to dashboard or authenticated page
  await expect(page).toHaveURL(/\/(dashboard|smart-add|lists)?$/);

  // Verify we're logged in by checking for authenticated elements
  await expect(page.getByRole('button', { name: 'Sign Out' })).toBeVisible({ timeout: 10000 });

  // Save signed-in state
  await page.context().storageState({ path: authFile });
});
