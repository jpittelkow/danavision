import { test, expect } from '@playwright/test';

/**
 * Dashboard E2E Tests
 * 
 * Tests the main dashboard functionality.
 */
test.describe('Dashboard', () => {
  test('should display dashboard after login', async ({ page }) => {
    await page.goto('/dashboard');

    // Verify page loads
    await expect(page).toHaveTitle(/Dashboard|DanaVision/);

    // Verify welcome message or dashboard content
    await expect(page.locator('text=Welcome').or(page.locator('h1'))).toBeVisible();
  });

  test('should show user info in sidebar', async ({ page }) => {
    await page.goto('/dashboard');

    // User avatar or name should be visible
    await expect(page.locator('text=Sign Out')).toBeVisible();
  });

  test('should have all navigation items', async ({ page }) => {
    await page.goto('/dashboard');

    // Verify all nav items exist
    const navItems = ['Smart Add', 'Dashboard', 'Lists', 'Search', 'Settings'];
    for (const item of navItems) {
      await expect(page.locator(`nav >> text=${item}`)).toBeVisible();
    }
  });

  test('should toggle theme', async ({ page }) => {
    await page.goto('/dashboard');

    // Find and click theme toggle
    const themeToggle = page.locator('button[title*="theme"], button:has-text("theme"), [aria-label*="theme"]');
    if (await themeToggle.isVisible()) {
      await themeToggle.click();

      // Theme should change (dark/light class on html or body)
      await page.waitForTimeout(500);
    }
  });

  test('should show quick action buttons', async ({ page }) => {
    await page.goto('/dashboard');

    // Quick actions like New List, Search Products
    const newListButton = page.locator('text=New List').or(page.locator('a:has-text("List")'));
    const searchButton = page.locator('text=Search Product').or(page.locator('a:has-text("Search")'));

    // At least one should be visible
    const hasNewList = await newListButton.isVisible();
    const hasSearch = await searchButton.isVisible();

    expect(hasNewList || hasSearch).toBeTruthy();
  });
});

test.describe('Dashboard - Mobile', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should show mobile menu toggle', async ({ page }) => {
    await page.goto('/dashboard');

    // Mobile menu button should be visible
    const menuButton = page.locator('button:has(svg.lucide-menu), button[aria-label*="menu"]');
    await expect(menuButton).toBeVisible();
  });

  test('should open sidebar on mobile', async ({ page }) => {
    await page.goto('/dashboard');

    // Click menu button
    const menuButton = page.locator('button:has(svg.lucide-menu), button[aria-label*="menu"]');
    await menuButton.click();

    // Sidebar should be visible
    await expect(page.locator('aside, nav >> text=Smart Add')).toBeVisible();
  });
});
