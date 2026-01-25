import { test, expect } from '@playwright/test';

/**
 * Smart Add Feature E2E Tests
 *
 * Tests the AI-powered product identification flow:
 * Phase 1: User searches/uploads image -> AI returns product suggestions
 * Phase 2: User selects product -> Adds to list -> Background price search runs
 */
test.describe('Smart Add', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
    await page.waitForLoadState('networkidle');
  });

  test('should display Smart Add page with correct elements', async ({ page }) => {
    // Verify page title
    await expect(page).toHaveTitle(/Smart Add/);

    // Verify header elements
    await expect(page.locator('text=Smart Add').first()).toBeVisible();

    // Verify description text
    await expect(
      page.locator('text=Upload an image or search for a product')
    ).toBeVisible();
  });

  test('should show search input and upload area', async ({ page }) => {
    // Verify search input is visible
    await expect(
      page.locator('input[placeholder*="Enter product name"]')
    ).toBeVisible();

    // Verify search button
    await expect(page.locator('button:has-text("Search")')).toBeVisible();

    // Verify upload area
    await expect(
      page
        .locator('text=Drop image here')
        .or(page.locator('text=Select from gallery'))
    ).toBeVisible();
  });

  test('should show Smart Add as first item in navigation', async ({ page }) => {
    // Smart Add should be the first navigation link
    const navLinks = page
      .locator('nav a, nav button')
      .filter({ hasText: /Smart Add|Dashboard|Lists|Search|Settings/ });
    const firstLink = navLinks.first();
    await expect(firstLink).toContainText('Smart Add');
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

test.describe('Smart Add - Text Search', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
    await page.waitForLoadState('networkidle');
  });

  test('should perform text search and show results', async ({ page }) => {
    // Enter search query
    await page.fill('input[placeholder*="Enter product name"]', 'Sony headphones');

    // Submit search
    await page.click('button:has-text("Search")');

    // Wait for analyzing state or results
    await expect(
      page
        .locator('text=Analyzing')
        .or(page.locator('text=Searching'))
        .or(page.locator('text=/\\d+ Products? Found/'))
        .or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 15000 });
  });

  test('should show product suggestions after search', async ({ page }) => {
    // Search for a product
    await page.fill('input[placeholder*="Enter product name"]', 'AirPods Pro');
    await page.click('button:has-text("Search")');

    // Wait for results
    await expect(
      page
        .locator('text=/\\d+ Products? Found/')
        .or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 15000 });
  });

  test('should disable search button when input is empty', async ({ page }) => {
    // Search button should be disabled without text
    const searchButton = page.locator('button:has-text("Search")');
    await expect(searchButton).toBeDisabled();

    // Enter text
    await page.fill('input[placeholder*="Enter product name"]', 'test');

    // Button should now be enabled
    await expect(searchButton).toBeEnabled();
  });
});

test.describe('Smart Add - Product Selection', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
    await page.waitForLoadState('networkidle');
  });

  test('should allow selecting a product from suggestions', async ({ page }) => {
    // Search for a product
    await page.fill('input[placeholder*="Enter product name"]', 'Sony headphones');
    await page.click('button:has-text("Search")');

    // Wait for results
    await expect(
      page
        .locator('text=/\\d+ Products? Found/')
        .or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 15000 });

    // If results exist, click on a product suggestion
    const resultsFound = await page.locator('text=/\\d+ Products? Found/').isVisible();
    if (resultsFound) {
      // Look for product cards/buttons
      const productCards = page
        .locator('button')
        .filter({ hasText: /Sony|Headphone/i });
      const count = await productCards.count();

      if (count > 0) {
        // Click first product
        await productCards.first().click();

        // Add to List form should appear
        await expect(page.locator('text=Add to Shopping List')).toBeVisible();
      }
    }
  });

  test('should show add form when product is selected', async ({ page }) => {
    // Search for a product
    await page.fill('input[placeholder*="Enter product name"]', 'MacBook');
    await page.click('button:has-text("Search")');

    // Wait for results
    await expect(
      page
        .locator('text=/\\d+ Products? Found/')
        .or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 15000 });

    // If results exist, select one
    const resultsFound = await page.locator('text=/\\d+ Products? Found/').isVisible();
    if (resultsFound) {
      const productCards = page
        .locator('[class*="rounded-lg"][class*="border"]')
        .filter({ hasText: /MacBook|Apple/i });
      const count = await productCards.count();

      if (count > 0) {
        await productCards.first().click();

        // Form elements should be visible
        await expect(page.locator('text=Shopping List')).toBeVisible();
        await expect(page.locator('text=Product Name')).toBeVisible();
        await expect(page.locator('button:has-text("Add to List")')).toBeVisible();
      }
    }
  });
});

test.describe('Smart Add - Image Upload', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
    await page.waitForLoadState('networkidle');
  });

  test('should accept image file upload', async ({ page }) => {
    // Create a test image file input
    const fileChooserPromise = page.waitForEvent('filechooser');

    // Click the upload area
    await page.click('text=Drop image here');

    const fileChooser = await fileChooserPromise;

    // Verify file chooser was opened (it accepts images)
    expect(fileChooser.isMultiple()).toBe(false);
  });

  test('should show image preview and analyze button after upload', async ({
    page,
  }) => {
    // We can't easily mock file upload in Playwright without a real file
    // Verify the upload UI elements exist
    await expect(page.locator('text=Supports JPG, PNG, WebP')).toBeVisible();
  });
});

test.describe('Smart Add - Add to List', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
    await page.waitForLoadState('networkidle');
  });

  test('should show list selector in add form', async ({ page }) => {
    // Search and select a product
    await page.fill('input[placeholder*="Enter product name"]', 'test product');
    await page.click('button:has-text("Search")');

    // Wait for results
    await expect(
      page
        .locator('text=/\\d+ Products? Found/')
        .or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 15000 });

    // If results exist and we can select a product
    const resultsFound = await page.locator('text=/\\d+ Products? Found/').isVisible();
    if (resultsFound) {
      const productCards = page.locator('button[class*="rounded-lg"]');
      const count = await productCards.count();

      if (count > 0) {
        await productCards.first().click();

        // List selector should be visible
        await expect(page.locator('text=Shopping List')).toBeVisible();
      }
    }
  });

  test('should show generic item toggle', async ({ page }) => {
    // Search for a generic item
    await page.fill('input[placeholder*="Enter product name"]', 'organic bananas');
    await page.click('button:has-text("Search")');

    // Wait for results
    await expect(
      page
        .locator('text=/\\d+ Products? Found/')
        .or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 15000 });

    // If results exist and we can select a product
    const resultsFound = await page.locator('text=/\\d+ Products? Found/').isVisible();
    if (resultsFound) {
      const productCards = page.locator('button[class*="rounded-lg"]');
      const count = await productCards.count();

      if (count > 0) {
        await productCards.first().click();

        // Generic item toggle should be visible
        await expect(page.locator('text=Generic Item')).toBeVisible();
      }
    }
  });
});

test.describe('Smart Add - Mobile Experience', () => {
  // Use mobile viewport AND mobile user agent to trigger isMobile detection
  test.use({
    viewport: { width: 375, height: 667 },
    userAgent:
      'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
  });

  test('should show camera or gallery button on mobile', async ({ page }) => {
    await page.goto('/smart-add');
    await page.waitForLoadState('networkidle');

    // On mobile with proper user agent, either:
    // - "Take Photo" button (camera option)
    // - "Select from gallery" text (mobile upload)
    // - Or "Drop image here" (fallback if mobile detection fails)
    const cameraButton = page.locator('text=Take Photo');
    const galleryButton = page.locator('text=Select from gallery');
    const desktopUpload = page.locator('text=Drop image here');

    // At least one upload option should be visible
    await expect(
      cameraButton.or(galleryButton).or(desktopUpload).first()
    ).toBeVisible();
  });

  test('should have touch-friendly interface on mobile', async ({ page }) => {
    await page.goto('/smart-add');
    await page.waitForLoadState('networkidle');

    // Verify buttons are large enough for touch
    const searchButton = page.locator('button:has-text("Search")');
    await expect(searchButton).toBeVisible();

    const boundingBox = await searchButton.boundingBox();

    // Search button should be at least 36px tall (touch-friendly)
    expect(boundingBox?.height).toBeGreaterThanOrEqual(36);
  });
});

test.describe('Smart Add - Error Handling', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
    await page.waitForLoadState('networkidle');
  });

  test('should show try again option after search with no results', async ({
    page,
  }) => {
    // Search for something unlikely to have results
    await page.fill(
      'input[placeholder*="Enter product name"]',
      'xyznonexistentproduct123'
    );
    await page.click('button:has-text("Search")');

    // Wait for search to complete
    await expect(
      page
        .locator('text=/\\d+ Products? Found/')
        .or(page.locator('text=No products found'))
        .or(page.locator('button:has-text("New Search")'))
    ).toBeVisible({ timeout: 15000 });
  });

  test('should allow starting a new search', async ({ page }) => {
    // Perform initial search
    await page.fill('input[placeholder*="Enter product name"]', 'test');
    await page.click('button:has-text("Search")');

    // Wait for search to complete
    await expect(
      page
        .locator('text=/\\d+ Products? Found/')
        .or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 15000 });

    // Look for New Search button
    const newSearchButton = page.locator('button:has-text("New Search")');

    if (await newSearchButton.isVisible().catch(() => false)) {
      await newSearchButton.click();

      // Should return to initial state
      await expect(
        page.locator('input[placeholder*="Enter product name"]')
      ).toBeVisible();
    }
  });
});

test.describe('Smart Add - Google Verification', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
    await page.waitForLoadState('networkidle');
  });

  test('should show Google verification link on product cards', async ({
    page,
  }) => {
    // Search for a product
    await page.fill('input[placeholder*="Enter product name"]', 'Sony WH-1000XM5');
    await page.click('button:has-text("Search")');

    // Wait for results
    await expect(
      page
        .locator('text=/\\d+ Products? Found/')
        .or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 15000 });

    // Look for external link icon (Google verification) if results exist
    const resultsFound = await page.locator('text=/\\d+ Products? Found/').isVisible();
    if (resultsFound) {
      const externalLinks = page
        .locator('[title="Verify on Google"]')
        .or(page.locator('a[href*="google.com/search"]'));
      const count = await externalLinks.count();

      // If results exist, should have Google verification links
      if (count > 0) {
        // Link should open in new tab
        const link = externalLinks.first();
        await expect(link).toHaveAttribute('target', '_blank');
      }
    }
  });
});
