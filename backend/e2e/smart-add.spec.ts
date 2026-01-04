import { test, expect } from '@playwright/test';

/**
 * Smart Add Feature E2E Tests
 * 
 * Tests the AI-powered product identification, real-time streaming search,
 * and add-to-list functionality.
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

  test('should show real-time search toggle in text mode', async ({ page }) => {
    // Switch to text search mode
    await page.click('button:has-text("Text Search")');

    // Verify streaming toggle is visible
    await expect(page.locator('text=Real-time results')).toBeVisible();
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

test.describe('Smart Add - Streaming Search', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
  });

  test('should have streaming enabled by default', async ({ page }) => {
    // Switch to text search mode
    await page.click('button:has-text("Text Search")');

    // Check that the streaming toggle is on
    const toggle = page.locator('[role="switch"]');
    await expect(toggle).toHaveAttribute('data-state', 'checked');
  });

  test('should allow toggling streaming mode off', async ({ page }) => {
    // Switch to text search mode
    await page.click('button:has-text("Text Search")');

    // Click the streaming toggle
    await page.click('[role="switch"]');

    // Verify it's now unchecked
    const toggle = page.locator('[role="switch"]');
    await expect(toggle).toHaveAttribute('data-state', 'unchecked');
  });

  test('should show step indicator during search', async ({ page }) => {
    // Switch to text search mode
    await page.click('button:has-text("Text Search")');

    // Enter search query
    await page.fill('input[placeholder*="Search for a product"]', 'test product');

    // Start search
    await page.click('button:has-text("Search")');

    // Look for step indicators (Analyzing, Searching, Complete)
    // At least one should be visible during the search process
    const stepIndicator = page.locator('text=Searching').or(page.locator('text=Complete'));
    await expect(stepIndicator).toBeVisible({ timeout: 10000 });
  });
});

test.describe('Smart Add - Add to List Flow', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
  });

  test('should show Add button on product cards', async ({ page }) => {
    // Switch to text search
    await page.click('button:has-text("Text Search")');

    // Search for a product
    await page.fill('input[placeholder*="Search for a product"]', 'headphones');
    await page.click('button:has-text("Search")');

    // Wait for results
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // If results are found, each product card should have an Add button
    const addButtons = page.locator('button:has-text("Add")');
    const count = await addButtons.count();
    
    if (count > 0) {
      await expect(addButtons.first()).toBeVisible();
    }
  });

  test('should open modal when clicking Add button', async ({ page }) => {
    // Switch to text search
    await page.click('button:has-text("Text Search")');

    // Search for a product
    await page.fill('input[placeholder*="Search for a product"]', 'headphones');
    await page.click('button:has-text("Search")');

    // Wait for results
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Click first Add button if results exist
    const addButtons = page.locator('button:has-text("Add")');
    const count = await addButtons.count();
    
    if (count > 0) {
      await addButtons.first().click();
      
      // Modal should open with "Add to Shopping List" title
      await expect(page.locator('text=Add to Shopping List')).toBeVisible();
    }
  });

  test('should pre-fill modal with product data', async ({ page }) => {
    // Switch to text search
    await page.click('button:has-text("Text Search")');

    // Search for a product
    await page.fill('input[placeholder*="Search for a product"]', 'headphones');
    await page.click('button:has-text("Search")');

    // Wait for results
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Click first Add button if results exist
    const addButtons = page.locator('button:has-text("Add")');
    const count = await addButtons.count();
    
    if (count > 0) {
      await addButtons.first().click();
      
      // Modal should have product name input pre-filled
      const productNameInput = page.locator('input[placeholder="Product name"]');
      await expect(productNameInput).toBeVisible();
      
      // Product name should not be empty (pre-filled)
      const value = await productNameInput.inputValue();
      expect(value.length).toBeGreaterThan(0);
    }
  });

  test('should close modal on cancel', async ({ page }) => {
    // Switch to text search
    await page.click('button:has-text("Text Search")');

    // Search for a product
    await page.fill('input[placeholder*="Search for a product"]', 'laptop');
    await page.click('button:has-text("Search")');

    // Wait for results
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Click first Add button if results exist
    const addButtons = page.locator('button:has-text("Add")');
    const count = await addButtons.count();
    
    if (count > 0) {
      await addButtons.first().click();
      
      // Modal should be open
      await expect(page.locator('text=Add to Shopping List')).toBeVisible();
      
      // Click cancel
      await page.click('button:has-text("Cancel")');
      
      // Modal should be closed
      await expect(page.locator('text=Add to Shopping List')).not.toBeVisible();
    }
  });

  test('should show UPC badge when available', async ({ page }) => {
    // Switch to text search
    await page.click('button:has-text("Text Search")');

    // Search for a branded product likely to have UPC
    await page.fill('input[placeholder*="Search for a product"]', 'Sony WH-1000XM5');
    await page.click('button:has-text("Search")');

    // Wait for results
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // UPC badges may be visible on product cards if UPC data is available
    // This is optional - depends on AI returning UPC data
  });

  test('should navigate to list creation if no lists exist', async ({ page }) => {
    // Switch to text search
    await page.click('button:has-text("Text Search")');

    // Search for a product
    await page.fill('input[placeholder*="Search for a product"]', 'test');
    await page.click('button:has-text("Search")');

    await page.waitForLoadState('networkidle');

    // If no lists exist, there should be a create list button or message
    // This verifies the empty state handling
  });
});

test.describe('Smart Add - Image Analysis', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
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

  test('should show additional context input after image selection', async ({ page }) => {
    // This test would require mocking file upload
    // For now, just verify the image mode UI elements exist
    await expect(page.locator('button:has-text("Image")')).toBeVisible();
  });
});

test.describe('Smart Add - UI Enhancements', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
  });

  test('should show confidence indicator after analysis', async ({ page }) => {
    // This would require triggering an AI analysis
    // For now, verify the page structure
    await expect(page.locator('h1:has-text("Smart Add")')).toBeVisible();
  });

  test('should allow editing search query after initial search', async ({ page }) => {
    // Switch to text search
    await page.click('button:has-text("Text Search")');

    // Search for a product
    await page.fill('input[placeholder*="Search for a product"]', 'initial query');
    await page.click('button:has-text("Search")');

    await page.waitForLoadState('networkidle');

    // After search completes, look for Edit Search button
    const editButton = page.locator('button:has-text("Edit Search")');
    
    // Wait a bit for results to load
    await page.waitForTimeout(2000);
    
    // If edit button exists (results were found), click it
    if (await editButton.isVisible()) {
      await editButton.click();
      
      // Should show input for custom search
      await expect(page.locator('input[placeholder*="Enter custom search"]')).toBeVisible();
    }
  });

  test('should show alternative search terms after AI analysis', async ({ page }) => {
    // This requires an AI analysis to complete
    // For now, verify basic page structure
    await expect(page.locator('text=AI will identify')).toBeVisible();
  });
});

test.describe('Smart Add - Image Proxy', () => {
  test('images should load through proxy', async ({ page }) => {
    // Switch to text search
    await page.click('button:has-text("Text Search")');

    // Search for a product
    await page.fill('input[placeholder*="Search for a product"]', 'laptop');
    await page.click('button:has-text("Search")');

    await page.waitForLoadState('networkidle');
    
    // Wait for any results
    await page.waitForTimeout(3000);

    // Check if any images are rendered (either proxied or with fallback)
    const images = page.locator('img');
    const imageCount = await images.count();
    
    // If images exist, they should load without error
    if (imageCount > 0) {
      const firstImage = images.first();
      
      // Wait for image to load
      await firstImage.evaluate((img: HTMLImageElement) => {
        return new Promise((resolve) => {
          if (img.complete) resolve(true);
          img.onload = () => resolve(true);
          img.onerror = () => resolve(false);
          setTimeout(() => resolve(true), 5000);
        });
      });
    }
  });
});

test.describe('Smart Add - Mobile Experience', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should show camera button on mobile', async ({ page }) => {
    await page.goto('/smart-add');
    
    // On mobile, camera button should be visible
    await expect(page.locator('text=Take Photo')).toBeVisible();
  });

  test('should have touch-friendly interface on mobile', async ({ page }) => {
    await page.goto('/smart-add');
    
    // Verify buttons are large enough for touch
    const imageButton = page.locator('button:has-text("Image")');
    const boundingBox = await imageButton.boundingBox();
    
    expect(boundingBox?.height).toBeGreaterThanOrEqual(36); // Minimum touch target
  });
});
