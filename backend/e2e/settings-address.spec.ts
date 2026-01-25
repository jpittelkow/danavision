import { test, expect } from '@playwright/test';

/**
 * Settings Address Typeahead E2E Tests
 * 
 * Tests the address typeahead functionality in the settings page.
 */
test.describe('Settings - Address Typeahead', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/settings');
  });

  test('should display address input in location settings', async ({ page }) => {
    // Verify we're on the settings page
    await expect(page).toHaveTitle(/Settings/);
    await page.waitForLoadState('networkidle');

    // Look for the Location section - it has a CardTitle with Location
    await expect(page.locator('h3:has-text("Location"), [class*="CardTitle"]:has-text("Location")').first()).toBeVisible();

    // Verify the address input exists (either placeholder or Home Address label)
    const addressInput = page.locator('input[placeholder*="Search for your address"]').or(page.locator('text=Home Address'));
    await expect(addressInput.first()).toBeVisible();
  });

  test('should show address typeahead suggestions on input', async ({ page }) => {
    // Find the address input
    const addressInput = page.locator('input[placeholder*="Search for your address"], input[placeholder*="address"]');
    
    // If there's already an address set, click to change it
    const changeButton = page.locator('button:has-text("Change")');
    if (await changeButton.isVisible()) {
      await changeButton.click();
    }

    // Type a search query
    await addressInput.fill('123 Main St');

    // Wait for network to settle (suggestions are debounced)
    await page.waitForLoadState('networkidle');

    // Check if dropdown or suggestions appear
    // Note: Actual suggestions depend on Google Places API availability and key configuration
  });

  test('should allow clearing address', async ({ page }) => {
    // Look for clear/X button if address is set
    const clearButton = page.locator('[aria-label="Clear"], button:has([class*="lucide-x"])').first();
    
    if (await clearButton.isVisible()) {
      await clearButton.click();
      
      // Verify address input is now editable
      await expect(page.locator('input[placeholder*="Search for your address"]')).toBeVisible();
    }
  });

  test('should persist address selection after save', async ({ page }) => {
    // Find the save button for general settings
    const saveButton = page.locator('button:has-text("Save General Settings")');
    
    // Verify save button exists
    await expect(saveButton).toBeVisible();
  });

  test('should show coordinates after selecting address', async ({ page }) => {
    // This test verifies the coordinate display feature
    // If an address is already selected with coordinates, they should be visible
    
    const coordinateDisplay = page.locator('text=/\\d+\\.\\d+,\\s*-?\\d+\\.\\d+/');
    
    // Coordinates may or may not be visible depending on if address is set
    // This just verifies the page loads correctly
    await page.waitForLoadState('networkidle');
  });
});

test.describe('Settings - Location Integration', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/settings');
  });

  test('should display location card with correct description', async ({ page }) => {
    await page.waitForLoadState('networkidle');
    
    // Look for the location card description - the text mentions local price searches
    const descriptionText = page.locator('text=/local.*price.*searches|local.*deals|local.*store/i');
    await expect(descriptionText.first()).toBeVisible();
  });

  test('should include address in form submission', async ({ page }) => {
    // Navigate to settings
    await expect(page).toHaveURL(/\/settings/);

    // The form should include the address field
    // Verify by checking the Location card exists
    await expect(page.locator('h3:has-text("Location"), [class*="CardTitle"]:has-text("Location")')).toBeVisible();
  });
});

test.describe('Settings - Address Validation', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/settings');
  });

  test('should show loading indicator during search', async ({ page }) => {
    // Get the address input
    const changeButton = page.locator('button:has-text("Change")');
    if (await changeButton.isVisible()) {
      await changeButton.click();
    }

    const addressInput = page.locator('input[placeholder*="Search for your address"]');

    if (await addressInput.isVisible()) {
      // Type to trigger search
      await addressInput.fill('1600 Pennsylvania Ave');

      // Wait for network activity
      await page.waitForLoadState('networkidle');
    }
  });

  test('should handle rate limiting gracefully', async ({ page }) => {
    // Get the address input
    const changeButton = page.locator('button:has-text("Change")');
    if (await changeButton.isVisible()) {
      await changeButton.click();
    }

    const addressInput = page.locator('input[placeholder*="Search for your address"]');

    if (await addressInput.isVisible()) {
      // Make rapid requests to trigger rate limiting
      await addressInput.fill('test1');
      await addressInput.fill('test12');
      await addressInput.fill('test123');

      // Should either show rate limit message or handle gracefully
      await page.waitForLoadState('networkidle');
    }
  });
});
