import { test, expect } from '@playwright/test';

/**
 * Nearby Store Discovery E2E Tests
 *
 * Tests the nearby store discovery feature that uses Google Places API.
 * Note: Some features require Google Places API key to be configured.
 */
test.describe('Nearby Store Discovery', () => {
  test.use({ storageState: 'e2e/.auth/user.json' });
  test.setTimeout(35000); // Settings tabs and dialogs can be slow

  test('shows Find Nearby Stores button in store settings', async ({ page }) => {
    // Navigate directly to stores tab using URL hash
    await page.goto('/settings#stores');
    await page.waitForLoadState('networkidle');

    // Wait for store preferences to load - use heading role for precise matching
    await expect(page.getByRole('heading', { name: 'Store Registry' })).toBeVisible();

    // Find the nearby stores button - be specific to avoid matching dialog title
    const nearbyButton = page.locator('button:has-text("Find Nearby Stores")');
    await expect(nearbyButton).toBeVisible();
  });

  test('opens nearby store discovery dialog', async ({ page }) => {
    await page.goto('/settings#stores');
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: 'Store Registry' })).toBeVisible();

    // Click the Find Nearby Stores button
    await page.locator('button:has-text("Find Nearby Stores")').click();

    // Dialog should open - check for dialog-specific content
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();

    // Dialog title should be visible
    await expect(dialog.getByRole('heading', { name: 'Find Nearby Stores' })).toBeVisible();
  });

  test('shows configuration required when API key missing', async ({ page }) => {
    await page.goto('/settings#stores');
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: 'Store Registry' })).toBeVisible();

    // Click the Find Nearby Stores button
    await page.locator('button:has-text("Find Nearby Stores")').click();

    // Wait for dialog to load
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();

    // Should show either configuration required OR the form (if API key is configured)
    // Check for either state - both are valid
    const configRequired = dialog.locator('text=Configuration Required');
    const searchRadiusLabel = dialog.locator('text=Search Radius');

    // One of these should be visible
    await expect(configRequired.or(searchRadiusLabel)).toBeVisible({ timeout: 5000 });
  });

  test('shows store discovery form when API key is configured', async ({ page }) => {
    await page.goto('/settings#stores');
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: 'Store Registry' })).toBeVisible();

    // Click the Find Nearby Stores button
    await page.locator('button:has-text("Find Nearby Stores")').click();

    // Wait for dialog to load
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();

    // Check if configuration is required
    const configRequired = await dialog.locator('text=Configuration Required').isVisible();

    if (configRequired) {
      // Skip form tests if API key not configured
      test.skip(true, 'Google Places API key not configured - skipping form tests');
      return;
    }

    // If configured, verify form elements
    await expect(dialog.locator('text=Search Radius')).toBeVisible({ timeout: 5000 });
    await expect(dialog.locator('text=Store Categories')).toBeVisible({ timeout: 5000 });
    await expect(dialog.locator('input[type="range"]')).toBeVisible({ timeout: 5000 });
    await expect(dialog.locator('button:has-text("Grocery Stores")')).toBeVisible({ timeout: 5000 });
    await expect(dialog.locator('button:has-text("Electronics")')).toBeVisible({ timeout: 5000 });
  });

  test('Google Places API key field exists in settings config tab', async ({ page }) => {
    await page.goto('/settings#configurations');
    await page.waitForLoadState('networkidle');

    // Wait for Google Places section (config tab may load in different order)
    await expect(
      page.locator('text=Nearby Store Discovery (Google Places)').or(page.locator('input#google_places_api_key'))
    ).toBeVisible({ timeout: 10000 });
    await expect(page.locator('input#google_places_api_key')).toBeVisible();
  });
});

test.describe('Nearby Store Discovery - Mobile', () => {
  test.use({
    viewport: { width: 375, height: 667 },
    storageState: 'e2e/.auth/user.json',
  });

  test('dialog is responsive on mobile', async ({ page }) => {
    // Navigate directly to stores tab using URL hash
    await page.goto('/settings#stores');
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: 'Store Registry' })).toBeVisible();

    // Button should still be visible and clickable
    const nearbyButton = page.locator('button:has-text("Find Nearby Stores")');
    await expect(nearbyButton).toBeVisible();

    await nearbyButton.click();

    // Dialog should fit on mobile screen
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();
  });
});
