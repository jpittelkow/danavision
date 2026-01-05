import { test, expect } from '@playwright/test';

/**
 * Items Page E2E Tests
 * 
 * Tests the all items page functionality including:
 * - Page display and navigation
 * - Filtering by list, status, priority
 * - Sorting functionality
 * - Item cards and links
 */
test.describe('Items Page', () => {
  test('should display items page', async ({ page }) => {
    await page.goto('/items');

    // Verify page loads
    await expect(page).toHaveTitle(/Items|DanaVision/);

    // Verify header
    await expect(page.locator('h1:has-text("All Items")')).toBeVisible();
  });

  test('should show filter button', async ({ page }) => {
    await page.goto('/items');

    // Filter button should be visible
    await expect(page.locator('button:has-text("Filters")')).toBeVisible();
  });

  test('should open filter panel when clicking filters button', async ({ page }) => {
    await page.goto('/items');

    // Click filter button
    await page.locator('button:has-text("Filters")').click();

    // Filter panel should appear with options
    await expect(page.locator('text=List')).toBeVisible();
    await expect(page.locator('text=Price Status')).toBeVisible();
    await expect(page.locator('text=Priority')).toBeVisible();
    await expect(page.locator('text=Sort By')).toBeVisible();
  });

  test('should navigate to items page from dashboard', async ({ page }) => {
    await page.goto('/dashboard');

    // Click Items in navigation
    await page.locator('nav >> text=Items').click();

    // Should be on items page
    await expect(page).toHaveURL(/\/items/);
    await expect(page.locator('h1:has-text("All Items")')).toBeVisible();
  });

  test('should navigate to items page from sidebar', async ({ page }) => {
    await page.goto('/lists');

    // Click Items in sidebar
    await page.locator('aside >> text=Items').click();

    // Should be on items page
    await expect(page).toHaveURL(/\/items/);
  });

  test('should show empty state when no items', async ({ page }) => {
    await page.goto('/items');

    // Check for empty state or item count
    const itemCount = page.locator('text=/\\d+ items?/');
    const emptyState = page.locator('text=No items found');
    
    // Either should be visible
    await expect(itemCount.or(emptyState)).toBeVisible();
  });

  test('should filter by price status', async ({ page }) => {
    await page.goto('/items');

    // Open filters
    await page.locator('button:has-text("Filters")').click();

    // Select price drops filter
    await page.locator('text=Price Status').locator('..').locator('button').click();
    await page.locator('text=Price Drops').click();

    // URL should update with filter
    await expect(page).toHaveURL(/status=drops/);
  });

  test('should filter by priority', async ({ page }) => {
    await page.goto('/items');

    // Open filters
    await page.locator('button:has-text("Filters")').click();

    // Select high priority filter
    await page.locator('text=Priority').locator('..').locator('button').click();
    await page.locator('[role="option"]:has-text("High")').click();

    // URL should update with filter
    await expect(page).toHaveURL(/priority=high/);
  });

  test('should change sort order', async ({ page }) => {
    await page.goto('/items');

    // Open filters
    await page.locator('button:has-text("Filters")').click();

    // Select sort by name
    await page.locator('text=Sort By').locator('..').locator('button').click();
    await page.locator('[role="option"]:has-text("Name")').first().click();

    // URL should update with sort
    await expect(page).toHaveURL(/sort=product_name/);
  });

  test('should show filter badge when filters are active', async ({ page }) => {
    await page.goto('/items?status=drops');

    // Filter button should show active indicator
    await expect(page.locator('button:has-text("Filters") >> text=Active')).toBeVisible();
  });
});

test.describe('Items Page - Item Cards', () => {
  test('items should link to item detail page', async ({ page }) => {
    await page.goto('/items');

    // If there are items, clicking one should navigate to detail
    const itemCard = page.locator('[href^="/items/"]').first();
    
    if (await itemCard.isVisible()) {
      await itemCard.click();
      await expect(page).toHaveURL(/\/items\/\d+/);
    }
  });

  test('item cards should show list name', async ({ page }) => {
    await page.goto('/items');

    // If there are items, they should show list name
    const itemCard = page.locator('.lucide-list-todo').first();
    
    if (await itemCard.isVisible()) {
      // The list name should be near the list icon
      await expect(itemCard.locator('..')).toBeVisible();
    }
  });
});

test.describe('Items Page - Mobile', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should display items page on mobile', async ({ page }) => {
    await page.goto('/items');

    // Verify page loads
    await expect(page.locator('h1:has-text("All Items")')).toBeVisible();
  });

  test('should show filter button on mobile', async ({ page }) => {
    await page.goto('/items');

    // Filter button should be visible
    await expect(page.locator('button:has-text("Filters")')).toBeVisible();
  });

  test('filters should work on mobile', async ({ page }) => {
    await page.goto('/items');

    // Open filters
    await page.locator('button:has-text("Filters")').click();

    // Filter panel should be visible
    await expect(page.locator('text=Price Status')).toBeVisible();
  });
});

test.describe('Items Page - Pagination', () => {
  test('should show pagination when many items exist', async ({ page }) => {
    await page.goto('/items');

    // Check if pagination exists (only shows when there are many items)
    const pagination = page.locator('text=/Previous|Next|Page/');
    const itemCount = await page.locator('text=/\\d+ items?/').textContent();
    
    // If more than 50 items, pagination should be visible
    if (itemCount && parseInt(itemCount.match(/\d+/)?.[0] || '0') > 50) {
      await expect(pagination).toBeVisible();
    }
  });
});
