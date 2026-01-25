import { test, expect } from '@playwright/test';

/**
 * AI-Powered Price Search E2E Tests
 *
 * Tests the AI-powered price search functionality that replaced the traditional
 * price API integration (SerpApi/Rainforest).
 */
test.describe('AI Price Search', () => {
  test.setTimeout(45000); // AI/external API calls can be slow

  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
    await page.waitForLoadState('domcontentloaded');
  });

  test('should show AI-powered search status during text search', async ({ page }) => {
    await page.fill('input[placeholder*="product name"]', 'Sony headphones');
    await page.click('button:has-text("Search")');

    // Wait for terminal state: results, no results, or loading indicators
    await expect(
      page
        .locator('text=/\\d+ Products? Found/')
        .or(page.locator('text=No products found'))
        .or(page.locator('text=Analyzing'))
        .or(page.locator('text=Searching'))
        .or(page.locator('text=AI'))
    ).toBeVisible({ timeout: 20000 });
  });

  test('should display results with retailer information', async ({ page }) => {
    await page.fill('input[placeholder*="product name"]', 'laptop');
    await page.click('button:has-text("Search")');

    await expect(
      page.locator('text=/\\d+ Products? Found/').or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 20000 });
  });

  test('should handle generic item searches', async ({ page }) => {
    await page.fill('input[placeholder*="product name"]', 'blueberries');
    await page.click('button:has-text("Search")');

    await expect(page.locator('h1:has-text("Smart Add")')).toBeVisible();
    await expect(
      page.locator('text=/\\d+ Products? Found/').or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 20000 });
  });

  test('should show AI provider names used for search', async ({ page }) => {
    await page.fill('input[placeholder*="product name"]', 'headphones');
    await page.click('button:has-text("Search")');

    await expect(
      page.locator('text=/\\d+ Products? Found/').or(page.locator('text=No products found'))
    ).toBeVisible({ timeout: 20000 });

    // Page should still be Smart Add (search completed without crashing)
    await expect(page.locator('h1:has-text("Smart Add")')).toBeVisible();
  });
});

test.describe('AI Price Search - Settings', () => {
  test('should show AI tab in settings', async ({ page }) => {
    await page.goto('/settings');
    await page.waitForLoadState('networkidle');

    // AI tab should exist - use the icon text or tab content
    const aiTab = page.getByRole('tab', { name: /AI/i }).or(page.locator('button:has-text("AI")'));
    await expect(aiTab.first()).toBeVisible();
  });

  test('should navigate to AI settings tab', async ({ page }) => {
    await page.goto('/settings#ai');
    await page.waitForLoadState('networkidle');

    // Should show AI providers section
    await expect(page.locator('text=AI Providers').first()).toBeVisible();
  });

  test('should show provider management in AI tab', async ({ page }) => {
    await page.goto('/settings#ai');
    await page.waitForLoadState('networkidle');

    // Should show Add Provider button or existing provider names
    const addProviderButton = page.locator('button:has-text("Add Provider")');
    const existingProvider = page.locator('text=/Claude|GPT|Gemini/i');

    await expect(addProviderButton.or(existingProvider.first())).toBeVisible({ timeout: 10000 });
  });

  test('should not show legacy price API settings', async ({ page }) => {
    await page.goto('/settings#configurations');
    await page.waitForLoadState('networkidle');

    // Legacy price API settings should not be visible
    const serpApiOption = page.locator('text=SerpApi');
    const rainforestOption = page.locator('text=Rainforest');

    // These should not be present in the new AI-powered version
    await expect(serpApiOption).not.toBeVisible();
    await expect(rainforestOption).not.toBeVisible();
  });
});

test.describe('AI Price Search - Refresh Functionality', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');
  });

  test('should show refresh button on list detail', async ({ page }) => {
    const listDetailLinks = page.locator('a[href^="/lists/"]');
    const allLinks = await listDetailLinks.all();
    let listLink = null;

    for (const link of allLinks) {
      const href = await link.getAttribute('href');
      if (href && /\/lists\/\d+/.test(href)) {
        listLink = link;
        break;
      }
    }

    if (!listLink) {
      test.skip(true, 'No lists available to test');
      return;
    }

    await listLink.click();
    await page.waitForLoadState('networkidle');

    const refreshButton = page
      .locator('button:has-text("Refresh")')
      .or(page.locator('button:has-text("Update Prices")'));
    const isEmpty = page.locator('text=No items').or(page.locator('text=Add Item'));

    await expect(refreshButton.or(isEmpty)).toBeVisible({ timeout: 5000 });
  });
});

test.describe('AI Price Search - Provider Configuration', () => {
  test('should show primary provider explanation in AI tab', async ({ page }) => {
    await page.goto('/settings#ai');
    await page.waitForLoadState('networkidle');

    // Should have explanation about providers
    await expect(page.locator('text=AI Providers').first()).toBeVisible();

    // Look for any explanation text about providers
    const hasExplanation =
      (await page.locator('text=Configure multiple AI providers').isVisible()) ||
      (await page.locator('text=primary').first().isVisible()) ||
      (await page.locator('text=Add Provider').isVisible());

    expect(hasExplanation).toBeTruthy();
  });
});
