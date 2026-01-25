import { test as setup, expect } from '@playwright/test';
import fs from 'fs';
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

  // Wait for login form to be interactive before filling
  const emailInput = page.locator('input[name="email"], input[type="email"]');
  await expect(emailInput).toBeVisible({ timeout: 10000 });

  // Fill in login credentials
  // Note: These should be test credentials - create a test user in your seeder
  const email = process.env.TEST_USER_EMAIL || 'test@example.com';
  const password = process.env.TEST_USER_PASSWORD || 'password';
  await emailInput.fill(email);
  await page.locator('input[name="password"], input[type="password"]').fill(password);

  // Submit the form
  const submitButton = page.locator('button[type="submit"]');
  await expect(submitButton).toBeEnabled();
  await submitButton.click();

  // Wait for navigation to dashboard or authenticated page
  await expect(page).toHaveURL(/\/(dashboard|smart-add|lists)?$/, { timeout: 15000 });

  // Verify we're logged in by checking for authenticated elements
  await expect(page.getByRole('button', { name: 'Sign Out' })).toBeVisible({ timeout: 10000 });

  // Ensure .auth directory exists before saving
  const authDir = path.join(__dirname, '.auth');
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  // Save signed-in state
  await page.context().storageState({ path: authFile });
});
