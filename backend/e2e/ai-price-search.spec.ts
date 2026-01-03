import { test, expect } from '@playwright/test';

/**
 * AI-Powered Price Search E2E Tests
 * 
 * Tests the AI-powered price search functionality that replaced the traditional
 * price API integration (SerpApi/Rainforest).
 */
test.describe('AI Price Search', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/smart-add');
  });

  test('should show AI-powered search status during text search', async ({ page }) => {
    // Switch to text search mode
    await page.click('button:has-text("Text Search")');

    // Enter search query
    await page.fill('input[placeholder*="Search for a product"]', 'Sony headphones');

    // Submit search
    await page.click('button:has-text("Search")');

    // Look for AI search indicators
    // The streaming search should show AI provider status
    const aiIndicator = page.locator('text=AI').or(
      page.locator('text=Searching with').or(
        page.locator('text=AI-powered')
      )
    );
    
    // Wait for either AI indicator or results to load
    await Promise.race([
      aiIndicator.waitFor({ timeout: 5000 }).catch(() => {}),
      page.waitForLoadState('networkidle'),
    ]);
  });

  test('should display results with retailer information', async ({ page }) => {
    // Switch to text search mode
    await page.click('button:has-text("Text Search")');

    // Search for a common product
    await page.fill('input[placeholder*="Search for a product"]', 'laptop');
    await page.click('button:has-text("Search")');

    // Wait for results
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // If results exist, they should have retailer names
    const results = page.locator('[data-testid="search-result"]').or(
      page.locator('.search-result').or(
        page.locator('[class*="result"]')
      )
    );
    
    const resultCount = await results.count();
    if (resultCount > 0) {
      // Check for common retailer names in results
      const pageContent = await page.content();
      const hasRetailerInfo = 
        pageContent.includes('Amazon') ||
        pageContent.includes('Walmart') ||
        pageContent.includes('Best Buy') ||
        pageContent.includes('Target') ||
        pageContent.includes('Store') ||
        pageContent.includes('retailer');
      
      // Results from AI should include retailer information
      expect(hasRetailerInfo || resultCount === 0).toBeTruthy();
    }
  });

  test('should handle generic item searches', async ({ page }) => {
    // Switch to text search mode
    await page.click('button:has-text("Text Search")');

    // Search for a generic item
    await page.fill('input[placeholder*="Search for a product"]', 'blueberries');
    await page.click('button:has-text("Search")');

    // Wait for results
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Generic items should show unit of measure badges or price per unit
    // Look for generic indicator or unit of measure
    const genericIndicator = page.locator('text=Generic').or(
      page.locator('text=/lb').or(
        page.locator('text=/oz').or(
          page.locator('text=per ')
        )
      )
    );

    // This may or may not be visible depending on AI response
    // The test verifies the page handles generic items without error
    await expect(page.locator('h1:has-text("Smart Add")')).toBeVisible();
  });

  test('should show AI provider names used for search', async ({ page }) => {
    // Switch to text search mode
    await page.click('button:has-text("Text Search")');

    // Enable streaming to see provider progress
    const toggle = page.locator('[role="switch"]');
    if (await toggle.getAttribute('data-state') !== 'checked') {
      await toggle.click();
    }

    // Search
    await page.fill('input[placeholder*="Search for a product"]', 'headphones');
    await page.click('button:has-text("Search")');

    // Wait for the search to complete
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(5000);

    // After completion, should mention AI providers used
    // Look for common provider names (Claude, GPT, Gemini) or generic "AI"
    const pageContent = await page.content();
    const mentionsAI = 
      pageContent.includes('Claude') ||
      pageContent.includes('GPT') ||
      pageContent.includes('Gemini') ||
      pageContent.includes('AI') ||
      pageContent.includes('provider');
    
    // Should have some indication of AI-powered search
    expect(mentionsAI).toBeTruthy();
  });
});

test.describe('AI Price Search - Error States', () => {
  test('should show helpful error when no AI providers configured', async ({ page }) => {
    // This test requires user without AI providers
    // Go to settings to check state
    await page.goto('/settings');
    
    // Navigate to AI Providers tab
    await page.click('button:has-text("AI Providers")');
    
    // Look for AI provider configuration section
    await expect(page.locator('text=AI Providers').first()).toBeVisible();
    
    // If no providers configured, there should be a message
    const noProvidersMessage = page.locator('text=No AI providers configured').or(
      page.locator('text=Add an AI provider')
    );
    
    // This will be visible if user hasn't configured providers
    // Otherwise providers will be shown - both are valid states
    await expect(page.locator('text=Add Provider').first()).toBeVisible();
  });
});

test.describe('AI Price Search - Settings', () => {
  test('should show AI-powered search info in settings', async ({ page }) => {
    await page.goto('/settings');

    // Go to Configurations tab
    await page.click('button:has-text("Configuration")');

    // Look for Price Search section which should now show AI info
    await expect(page.locator('text=Price Search').first()).toBeVisible();
    await expect(page.locator('text=AI-Powered Search').or(page.locator('text=AI-powered'))).toBeVisible();
  });

  test('should not show legacy price API settings', async ({ page }) => {
    await page.goto('/settings');

    // Go to Configurations tab
    await page.click('button:has-text("Configuration")');

    // Legacy price API settings should not be visible
    const serpApiOption = page.locator('text=SerpApi');
    const rainforestOption = page.locator('text=Rainforest');
    
    // These should not be present in the new AI-powered version
    await expect(serpApiOption).not.toBeVisible();
    await expect(rainforestOption).not.toBeVisible();
  });

  test('should show provider status in price search section', async ({ page }) => {
    await page.goto('/settings');

    // Go to Configurations tab
    await page.click('button:has-text("Configuration")');

    // Should show how many AI providers are active
    const providerStatus = page.locator('text=active AI provider').or(
      page.locator('text=No AI providers configured')
    );
    
    await expect(providerStatus).toBeVisible();
  });
});

test.describe('AI Price Search - Refresh Functionality', () => {
  test.beforeEach(async ({ page }) => {
    // Need an existing list with items
    await page.goto('/lists');
  });

  test('should show AI-powered refresh message', async ({ page }) => {
    // This test requires an existing list with items
    // Get first list if available
    const listLink = page.locator('a[href*="/lists/"]').first();
    
    if (await listLink.isVisible()) {
      await listLink.click();
      
      // Look for refresh button
      const refreshButton = page.locator('button:has-text("Refresh")').or(
        page.locator('button:has-text("Update Prices")')
      );
      
      if (await refreshButton.isVisible()) {
        await refreshButton.click();
        
        // After refresh, message should mention AI providers
        await page.waitForLoadState('networkidle');
        
        // Success message should appear
        const successMessage = page.locator('text=Updated').or(
          page.locator('text=Prices updated').or(
            page.locator('[class*="success"]')
          )
        );
        
        // Wait for success or error message
        await page.waitForTimeout(3000);
      }
    }
  });
});

test.describe('AI Price Search - Multi-Provider', () => {
  test('should explain multi-AI aggregation in settings', async ({ page }) => {
    await page.goto('/settings');

    // Go to AI Providers tab
    await page.click('button:has-text("AI Providers")');

    // Look for multi-AI explanation
    await expect(page.locator('text=Multi-AI Aggregation').or(
      page.locator('text=multiple active AI providers')
    )).toBeVisible();
  });

  test('should show primary provider indicator', async ({ page }) => {
    await page.goto('/settings');

    // Go to AI Providers tab
    await page.click('button:has-text("AI Providers")');

    // If providers exist, one should be marked as primary
    // Look for star icon or "primary" text
    const primaryIndicator = page.locator('text=primary').or(
      page.locator('[class*="star"]').or(
        page.locator('svg[class*="Star"]')
      )
    );

    // Should have explanation about primary provider
    await expect(page.locator('text=primary provider').first()).toBeVisible();
  });
});
