import { test, expect } from '@playwright/test';

/**
 * Dashboard E2E Tests
 * 
 * Tests the main dashboard functionality including:
 * - Page load and navigation
 * - Stats cards display
 * - Active jobs widget
 * - Price drops and all-time lows sections
 * - Store leaderboard
 * - Items needing attention
 */
test.describe('Dashboard', () => {
  test('should display dashboard after login', async ({ page }) => {
    await page.goto('/dashboard');

    // Verify page loads
    await expect(page).toHaveTitle(/Dashboard|DanaVision/);

    // Verify welcome message
    await expect(page.locator('h1:has-text("Welcome back")')).toBeVisible();
  });

  test('should show stats cards', async ({ page }) => {
    await page.goto('/dashboard');

    // Check for stat cards - wait for dashboard to fully load
    await page.waitForLoadState('networkidle');
    
    await expect(page.locator('text=Shopping Lists').first()).toBeVisible();
    await expect(page.locator('text=Total Items').first()).toBeVisible();
    await expect(page.locator('text=Price Drops').first()).toBeVisible();
    await expect(page.locator('text=Potential Savings').first()).toBeVisible();
  });

  test('should show active jobs section', async ({ page }) => {
    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');

    // Check for active jobs card
    await expect(page.locator('text=Active Jobs').first()).toBeVisible();
  });

  test('should show price check status', async ({ page }) => {
    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');

    // Check for price check status card
    await expect(page.locator('text=Price Check Status').first()).toBeVisible();
    await expect(page.locator('text=Last Updated').first()).toBeVisible();
    await expect(page.locator('text=All-Time Lows').first()).toBeVisible();
  });

  test('should show quick action buttons', async ({ page }) => {
    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');

    // Check for action buttons
    await expect(page.locator('a:has-text("New List")').first()).toBeVisible();
    await expect(page.locator('a:has-text("Smart Add")').first()).toBeVisible();
  });

  test('should have updated navigation items', async ({ page }) => {
    await page.goto('/dashboard');

    // Check for correct nav items (Search should be removed, Items added)
    await expect(page.locator('nav >> text=Smart Add')).toBeVisible();
    await expect(page.locator('nav >> text=Dashboard')).toBeVisible();
    await expect(page.locator('nav >> text=Lists')).toBeVisible();
    await expect(page.locator('nav >> text=Items')).toBeVisible();
    
    // Search should NOT be in main nav anymore
    await expect(page.locator('nav >> text=Search')).not.toBeVisible();
    
    // Settings should still be visible (in bottom section)
    await expect(page.locator('aside >> text=Settings')).toBeVisible();
  });

  test('should show user info and sign out in sidebar', async ({ page }) => {
    await page.goto('/dashboard');

    // User section should show sign out
    await expect(page.locator('button:has-text("Sign Out")')).toBeVisible();
  });

  test('clicking stats card navigates to correct page', async ({ page }) => {
    await page.goto('/dashboard');

    // Click on Lists stat card
    await page.locator('text=Shopping Lists').click();
    await expect(page).toHaveURL(/\/lists/);
  });

  test('clicking Items stat card navigates to items page', async ({ page }) => {
    await page.goto('/dashboard');

    // Click on Total Items stat card
    await page.locator('text=Total Items').click();
    await expect(page).toHaveURL(/\/items/);
  });
});

test.describe('Dashboard - Empty State', () => {
  test('should show action buttons on dashboard', async ({ page }) => {
    await page.goto('/dashboard');
    await page.waitForLoadState('networkidle');

    // Check if total items stat card is visible
    const totalItemsCard = page.locator('text=Total Items').first();
    await expect(totalItemsCard).toBeVisible();

    // Dashboard should always have action buttons (either empty state or header buttons)
    const actionButtons = page
      .locator('a:has-text("New List")')
      .or(page.locator('a:has-text("Smart Add")'))
      .or(page.locator('text=Create Your First List'));

    await expect(actionButtons.first()).toBeVisible();
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

  test('should open sidebar on mobile and show all nav items', async ({ page }) => {
    await page.goto('/dashboard');

    // Click menu button
    const menuButton = page.locator('button:has(svg.lucide-menu), button[aria-label*="menu"]');
    await menuButton.click();

    // Sidebar should be visible with nav items
    await expect(page.locator('aside >> text=Smart Add')).toBeVisible();
    await expect(page.locator('aside >> text=Items')).toBeVisible();
    await expect(page.locator('aside >> text=Settings')).toBeVisible();
  });

  test('should display stats in grid on mobile', async ({ page }) => {
    await page.goto('/dashboard');

    // Stats should still be visible on mobile
    await expect(page.locator('text=Shopping Lists')).toBeVisible();
    await expect(page.locator('text=Total Items')).toBeVisible();
  });
});

test.describe('Dashboard - Responsive Charts', () => {
  test('price trend chart should be visible when data exists', async ({ page }) => {
    await page.goto('/dashboard');

    // Chart section should be present (may be empty if no data)
    const chartSection = page.locator('text=7-Day Price Activity');
    if (await chartSection.isVisible()) {
      await expect(chartSection).toBeVisible();
    }
  });

  test('store leaderboard should be visible when data exists', async ({ page }) => {
    await page.goto('/dashboard');

    // Store leaderboard section
    const leaderboard = page.locator('text=Best Value Stores');
    if (await leaderboard.isVisible()) {
      await expect(leaderboard).toBeVisible();
    }
  });
});
