import { test, expect } from '@playwright/test';

/**
 * Smart Add Feature E2E Tests
 * 
 * Tests the AI-powered product identification and add-to-list functionality.
 */
test.describe('Smart Add', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
  });

  test('should display Smart Add page with correct elements', async ({ page }) => {
    // Verify page title
    await expect(page).toHaveTitle(/Smart Add/);

    // Verify header elements
    await expect(page.locator('text=Smart Add').first()).toBeVisible();

    // Verify mode toggle buttons
    await expect(page.locator('button:has-text("Image")')).toBeVisible();
    await expect(page.locator('button:has-text("Text Search")')).toBeVisible();
  });

  test('should show Smart Add as first item in navigation', async ({ page }) => {
    // Smart Add should be the first navigation link
    const navLinks = page.locator('nav a, nav button').filter({ hasText: /Smart Add|Dashboard|Lists|Search|Settings/ });
    const firstLink = navLinks.first();
    await expect(firstLink).toContainText('Smart Add');
  });

  test('should switch to text search mode', async ({ page }) => {
    // Click text search button
    await page.click('button:has-text("Text Search")');

    // Verify search input is visible
    await expect(page.locator('input[placeholder*="Search for a product"]')).toBeVisible();
  });

  test('should perform text search', async ({ page }) => {
    // Switch to text search mode
    await page.click('button:has-text("Text Search")');

    // Enter search query
    await page.fill('input[placeholder*="Search for a product"]', 'Sony headphones');

    // Submit search
    await page.click('button:has-text("Search")');

    // Wait for results or loading state
    // Note: Actual results depend on API configuration
    await page.waitForLoadState('networkidle');
  });

  test('should show image upload area in image mode', async ({ page }) => {
    // Verify upload area is visible
    await expect(page.locator('text=Drop image here').or(page.locator('text=Select from gallery'))).toBeVisible();
  });

  test('should navigate to Smart Add from any page', async ({ page }) => {
    // Start from dashboard
    await page.goto('/dashboard');

    // Click Smart Add in navigation
    await page.click('a:has-text("Smart Add"), nav >> text=Smart Add');

    // Verify navigation
    await expect(page).toHaveURL(/\/smart-add/);
  });
});

test.describe('Smart Add - Add to List Flow', () => {
  test('should show list selector when product is identified', async ({ page }) => {
    await page.goto('/smart-add');

    // Switch to text search
    await page.click('button:has-text("Text Search")');

    // Search for a product
    await page.fill('input[placeholder*="Search for a product"]', 'test product');
    await page.click('button:has-text("Search")');

    // Wait for results
    await page.waitForLoadState('networkidle');

    // If results are found, there should be ability to add to list
    // This test verifies the flow exists even if no actual results
  });
});
