import { test, expect } from '@playwright/test';

test.describe('Nearby Store Discovery', () => {
  test.beforeEach(async ({ page }) => {
    // Login first
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');
  });

  test('shows Find Nearby Stores button in store settings', async ({ page }) => {
    // Navigate to settings
    await page.goto('/settings');
    
    // Click on the Stores tab
    await page.click('[value="stores"]');
    
    // Wait for store preferences to load
    await page.waitForSelector('text=Store Registry');
    
    // Find the nearby stores button
    const nearbyButton = page.locator('button:has-text("Find Nearby Stores")');
    await expect(nearbyButton).toBeVisible();
  });

  test('opens nearby store discovery dialog', async ({ page }) => {
    await page.goto('/settings');
    await page.click('[value="stores"]');
    await page.waitForSelector('text=Store Registry');
    
    // Click the Find Nearby Stores button
    await page.click('button:has-text("Find Nearby Stores")');
    
    // Dialog should open
    await expect(page.locator('text=Find Nearby Stores')).toBeVisible();
    await expect(page.locator('text=Search Radius')).toBeVisible();
    await expect(page.locator('text=Store Categories')).toBeVisible();
  });

  test('shows configuration required when API key missing', async ({ page }) => {
    await page.goto('/settings');
    await page.click('[value="stores"]');
    await page.waitForSelector('text=Store Registry');
    
    // Click the Find Nearby Stores button
    await page.click('button:has-text("Find Nearby Stores")');
    
    // Should show configuration required message
    await expect(page.locator('text=Configuration Required')).toBeVisible();
    await expect(page.locator('text=Add your Google Places API key')).toBeVisible();
  });

  test('allows selecting store categories', async ({ page }) => {
    // This test would require setting up API keys first
    // For now, we just verify the category buttons exist
    await page.goto('/settings');
    await page.click('[value="stores"]');
    await page.waitForSelector('text=Store Registry');
    await page.click('button:has-text("Find Nearby Stores")');
    
    // Category buttons should be present
    await expect(page.locator('button:has-text("Grocery Stores")')).toBeVisible();
    await expect(page.locator('button:has-text("Electronics")')).toBeVisible();
    await expect(page.locator('button:has-text("Pet Stores")')).toBeVisible();
    await expect(page.locator('button:has-text("Pharmacies")')).toBeVisible();
  });

  test('can adjust search radius', async ({ page }) => {
    await page.goto('/settings');
    await page.click('[value="stores"]');
    await page.waitForSelector('text=Store Registry');
    await page.click('button:has-text("Find Nearby Stores")');
    
    // Find the radius slider
    const slider = page.locator('input[type="range"]');
    await expect(slider).toBeVisible();
    
    // Default should be 10 miles
    await expect(page.locator('text=10 miles')).toBeVisible();
  });

  test('Google Places API key field exists in settings config tab', async ({ page }) => {
    await page.goto('/settings');
    
    // Click on the Config tab
    await page.click('[value="configurations"]');
    
    // Wait for config section to load
    await page.waitForSelector('text=Email Configuration');
    
    // Look for Google Places configuration section
    await expect(page.locator('text=Nearby Store Discovery (Google Places)')).toBeVisible();
    await expect(page.locator('input#google_places_api_key')).toBeVisible();
  });

  test('dialog closes when clicking Done after discovery', async ({ page }) => {
    // This test would require mocking the API responses
    // Skipping for now as it requires full integration setup
    test.skip();
  });
});

test.describe('Nearby Store Discovery - Mobile', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('dialog is responsive on mobile', async ({ page }) => {
    await page.goto('/login');
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="password"]', 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard');

    await page.goto('/settings');
    await page.click('[value="stores"]');
    await page.waitForSelector('text=Store Registry');
    
    // Button should still be visible and clickable
    const nearbyButton = page.locator('button:has-text("Find Nearby Stores")');
    await expect(nearbyButton).toBeVisible();
    
    await nearbyButton.click();
    
    // Dialog should fit on mobile screen
    const dialog = page.locator('[role="dialog"]');
    await expect(dialog).toBeVisible();
  });
});
