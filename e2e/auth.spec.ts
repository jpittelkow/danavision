import { test, expect } from '@playwright/test';

test.describe('Authentication', () => {
  test.describe('Login Page', () => {
    test('displays login form', async ({ page }) => {
      await page.goto('/login');

      await expect(page.getByRole('heading', { name: /sign in/i })).toBeVisible();
      await expect(page.getByLabel(/email/i)).toBeVisible();
      await expect(page.getByLabel(/password/i)).toBeVisible();
      await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible();
    });

    test('shows validation errors for empty form', async ({ page }) => {
      await page.goto('/login');

      await page.getByRole('button', { name: /sign in/i }).click();

      await expect(page.getByText(/email is required/i)).toBeVisible();
      await expect(page.getByText(/password is required/i)).toBeVisible();
    });

    test('shows error for invalid credentials', async ({ page }) => {
      await page.goto('/login');

      await page.getByLabel(/email/i).fill('invalid@example.com');
      await page.getByLabel(/password/i).fill('wrongpassword');
      await page.getByRole('button', { name: /sign in/i }).click();

      // Wait for error message
      await expect(page.getByText(/invalid credentials|unauthorized/i)).toBeVisible({ timeout: 10000 });
    });

    test('has link to registration page', async ({ page }) => {
      await page.goto('/login');

      const registerLink = page.getByRole('link', { name: /sign up|register|create account/i });
      await expect(registerLink).toBeVisible();
      
      await registerLink.click();
      await expect(page).toHaveURL(/\/register/);
    });

    test('has link to forgot password', async ({ page }) => {
      await page.goto('/login');

      const forgotLink = page.getByRole('link', { name: /forgot password/i });
      await expect(forgotLink).toBeVisible();
      
      await forgotLink.click();
      await expect(page).toHaveURL(/\/forgot-password/);
    });
  });

  test.describe('Registration Page', () => {
    test('displays registration form', async ({ page }) => {
      await page.goto('/register');

      await expect(page.getByRole('heading', { name: /sign up|register|create account/i })).toBeVisible();
      await expect(page.getByLabel(/name/i)).toBeVisible();
      await expect(page.getByLabel(/email/i)).toBeVisible();
      await expect(page.getByLabel(/^password$/i)).toBeVisible();
      await expect(page.getByLabel(/confirm password/i)).toBeVisible();
    });

    test('shows validation errors for empty form', async ({ page }) => {
      await page.goto('/register');

      await page.getByRole('button', { name: /sign up|register|create account/i }).click();

      await expect(page.getByText(/name is required/i)).toBeVisible();
      await expect(page.getByText(/email is required/i)).toBeVisible();
    });

    test('shows error for password mismatch', async ({ page }) => {
      await page.goto('/register');

      await page.getByLabel(/name/i).fill('Test User');
      await page.getByLabel(/email/i).fill('test@example.com');
      await page.getByLabel(/^password$/i).fill('password123');
      await page.getByLabel(/confirm password/i).fill('different123');

      await page.getByRole('button', { name: /sign up|register|create account/i }).click();

      await expect(page.getByText(/passwords don't match|passwords do not match/i)).toBeVisible();
    });

    test('has link to login page', async ({ page }) => {
      await page.goto('/register');

      const loginLink = page.getByRole('link', { name: /sign in|log in|already have an account/i });
      await expect(loginLink).toBeVisible();
      
      await loginLink.click();
      await expect(page).toHaveURL(/\/login/);
    });
  });

  test.describe('Forgot Password Page', () => {
    test('displays forgot password form', async ({ page }) => {
      await page.goto('/forgot-password');

      await expect(page.getByRole('heading', { name: /forgot password|reset password/i })).toBeVisible();
      await expect(page.getByLabel(/email/i)).toBeVisible();
      await expect(page.getByRole('button', { name: /send|reset|submit/i })).toBeVisible();
    });

    test('shows validation error for invalid email', async ({ page }) => {
      await page.goto('/forgot-password');

      await page.getByLabel(/email/i).fill('invalid-email');
      await page.getByRole('button', { name: /send|reset|submit/i }).click();

      await expect(page.getByText(/invalid email|enter a valid email/i)).toBeVisible();
    });
  });

  test.describe('Protected Routes', () => {
    test('redirects to login when not authenticated', async ({ page }) => {
      await page.goto('/dashboard');

      // Should redirect to login
      await expect(page).toHaveURL(/\/login/);
    });

    test('redirects to login when accessing settings', async ({ page }) => {
      await page.goto('/settings');

      // Should redirect to login
      await expect(page).toHaveURL(/\/login/);
    });
  });

  test.describe('Successful Auth Flows', () => {
    const password = 'Password123!';

    test('successful registration redirects to dashboard', async ({ page }) => {
      const uniqueEmail = `test-reg-${Date.now()}@example.com`;
      await page.goto('/register');

      await page.getByLabel(/name/i).fill('E2E Test User');
      await page.getByLabel(/email/i).fill(uniqueEmail);
      await page.getByLabel(/^password$/i).fill(password);
      await page.getByLabel(/confirm password/i).fill(password);

      await page.getByRole('button', { name: /sign up|register|create account/i }).click();

      await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });
    });

    test('successful login redirects to dashboard', async ({ page }) => {
      const uniqueEmail = `test-login-${Date.now()}@example.com`;
      await page.goto('/register');
      await page.getByLabel(/name/i).fill('Login Test User');
      await page.getByLabel(/email/i).fill(uniqueEmail);
      await page.getByLabel(/^password$/i).fill(password);
      await page.getByLabel(/confirm password/i).fill(password);
      await page.getByRole('button', { name: /sign up|register|create account/i }).click();
      await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });

      await page.getByRole('button', { name: /login test user/i }).first().click();
      await page.getByRole('menuitem', { name: /sign out/i }).click();
      await expect(page).toHaveURL(/\/login/, { timeout: 5000 });

      await page.getByLabel(/email/i).fill(uniqueEmail);
      await page.getByLabel(/password/i).fill(password);
      await page.getByRole('button', { name: /sign in/i }).click();
      await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });
    });
  });
});
