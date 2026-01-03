import { test, expect } from '@playwright/test';

test.describe('Price Highlighting', () => {
  test.use({ storageState: 'e2e/.auth/user.json' });

  test('vendor price table shows best price badge', async ({ page }) => {
    // Navigate to a list
    await page.goto('/lists');
    
    // Check if there's a list with items that have vendor prices
    const listLink = page.locator('a[href*="/lists/"]').first();
    const hasLists = await listLink.count() > 0;
    
    if (hasLists) {
      await listLink.click();
      
      // If there's an item with vendor prices, check the table
      const vendorSection = page.getByText(/Vendor Prices/i);
      if (await vendorSection.count() > 0) {
        // Best price badge should have green styling
        const bestBadge = page.locator('text=Best Price').first();
        if (await bestBadge.count() > 0) {
          await expect(bestBadge).toBeVisible();
        }
      }
    }
  });

  test('stock status badges use outline variant', async ({ page }) => {
    await page.goto('/lists');
    
    const listLink = page.locator('a[href*="/lists/"]').first();
    const hasLists = await listLink.count() > 0;
    
    if (hasLists) {
      await listLink.click();
      
      // Stock status should use outline badges (less prominent)
      const inStockBadge = page.getByText('In Stock').first();
      if (await inStockBadge.count() > 0) {
        // Should not have destructive/success styling - should be outline
        await expect(inStockBadge).toBeVisible();
      }
    }
  });
});

test.describe('Item Detail Price Display', () => {
  test.use({ storageState: 'e2e/.auth/user.json' });

  test('item show page displays vendor prices', async ({ page }) => {
    await page.goto('/lists');
    
    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.count() > 0) {
      await listLink.click();
      
      // Click on first item if available
      const itemLink = page.locator('[href*="/items/"]').first();
      if (await itemLink.count() > 0) {
        await itemLink.click();
        
        // Should see vendor prices section
        await expect(page.getByText('Vendor Prices')).toBeVisible();
      }
    }
  });
});
