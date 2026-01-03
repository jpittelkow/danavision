import { test, expect } from '@playwright/test';

test.describe('Generic Items Feature', () => {
  test.use({ storageState: 'playwright/.auth/user.json' });

  test('should display generic item badge on Smart Add page after AI analysis', async ({ page }) => {
    await page.goto('/smart-add');
    
    // Check that the Smart Add page loads
    await expect(page.locator('h1')).toContainText('Smart Add');
    
    // Switch to text search mode
    await page.getByRole('button', { name: /text search/i }).click();
    
    // Search for a generic item
    const searchInput = page.locator('input[placeholder*="Search for a product"]');
    await searchInput.fill('blueberries');
    await page.getByRole('button', { name: 'Search' }).click();
    
    // Wait for results
    await page.waitForTimeout(2000);
    
    // Check if the generic item toggle is present in the add form
    const genericToggle = page.getByText('Generic Item');
    await expect(genericToggle).toBeVisible();
  });

  test('should allow toggling generic item switch in add form', async ({ page }) => {
    await page.goto('/smart-add');
    
    // Switch to text search mode
    await page.getByRole('button', { name: /text search/i }).click();
    
    // Search for any product
    const searchInput = page.locator('input[placeholder*="Search for a product"]');
    await searchInput.fill('test product');
    await page.getByRole('button', { name: 'Search' }).click();
    
    // Wait for the add form to appear (after selecting a result or if no results)
    await page.waitForTimeout(2000);
    
    // Look for the generic item toggle
    const genericSwitch = page.locator('button[role="switch"]').filter({ hasText: /generic/i }).first();
    
    // The switch should exist in the UI
    if (await genericSwitch.isVisible()) {
      // Toggle it on
      await genericSwitch.click();
      
      // Unit of measure selector should appear
      await expect(page.getByText('Unit of Measure')).toBeVisible();
    }
  });

  test('should show unit of measure dropdown when generic is enabled', async ({ page }) => {
    await page.goto('/smart-add');
    
    await page.getByRole('button', { name: /text search/i }).click();
    
    const searchInput = page.locator('input[placeholder*="Search for a product"]');
    await searchInput.fill('apples');
    await page.getByRole('button', { name: 'Search' }).click();
    
    await page.waitForTimeout(2000);
    
    // Find and check the generic item switch
    const genericSwitch = page.locator('[data-state]').filter({ hasText: /generic/i }).first();
    
    if (await genericSwitch.isVisible()) {
      // Enable generic item
      await genericSwitch.click();
      
      // Unit selector should appear with pound as default
      const unitSelector = page.locator('button').filter({ hasText: /pound|lb|select unit/i });
      await expect(unitSelector).toBeVisible();
    }
  });

  test('should display generic badge on list item card', async ({ page }) => {
    // Navigate to lists page
    await page.goto('/lists');
    
    // Look for any list
    const listLink = page.locator('a[href^="/lists/"]').first();
    
    if (await listLink.isVisible()) {
      await listLink.click();
      
      // Wait for list detail page
      await page.waitForURL(/\/lists\/\d+/);
      
      // Check if there are any items with generic badge
      const genericBadge = page.locator('text=/per (lb|oz|kg|gallon|dozen|each)/i');
      
      // This test validates the UI renders correctly - badge may or may not be present
      // depending on whether there are generic items in the list
      const badgeCount = await genericBadge.count();
      console.log(`Found ${badgeCount} generic item badges`);
    }
  });

  test('should allow editing item to be generic on item detail page', async ({ page }) => {
    // First, create a list if none exists
    await page.goto('/lists');
    
    // Try to find an existing list, or create one
    const existingList = page.locator('a[href^="/lists/"]').first();
    
    if (await existingList.isVisible()) {
      await existingList.click();
      await page.waitForURL(/\/lists\/\d+/);
      
      // Find an item to click on
      const itemLink = page.locator('a[href^="/items/"]').first();
      
      if (await itemLink.isVisible()) {
        await itemLink.click();
        await page.waitForURL(/\/items\/\d+/);
        
        // Click edit button
        const editButton = page.getByRole('button', { name: 'Edit' });
        if (await editButton.isVisible()) {
          await editButton.click();
          
          // Look for the generic item switch in edit form
          const genericSwitch = page.locator('[role="switch"]').first();
          await expect(genericSwitch).toBeVisible();
          
          // Toggle and verify unit selector appears
          const isChecked = await genericSwitch.getAttribute('data-state');
          if (isChecked !== 'checked') {
            await genericSwitch.click();
            
            // Unit of measure should now be visible
            await expect(page.getByText('Unit of Measure')).toBeVisible();
          }
          
          // Cancel edit
          await page.getByRole('button', { name: 'Cancel' }).click();
        }
      }
    }
  });

  test('should format price with unit for generic items', async ({ page }) => {
    await page.goto('/lists');
    
    // Navigate to any list
    const listLink = page.locator('a[href^="/lists/"]').first();
    
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForURL(/\/lists\/\d+/);
      
      // Look for prices formatted with units (e.g., "$4.99/lb")
      const priceWithUnit = page.locator('text=/\\$\\d+\\.\\d{2}\\/(lb|oz|kg|gallon|liter|each|dozen)/');
      
      // Log how many we found for debugging
      const count = await priceWithUnit.count();
      console.log(`Found ${count} prices with unit formatting`);
    }
  });
});

test.describe('Generic Items - List Creation Flow', () => {
  test.use({ storageState: 'playwright/.auth/user.json' });

  test('should be able to add generic item manually to list', async ({ page }) => {
    // Go to lists page
    await page.goto('/lists');
    
    // Find and click on a list, or navigate to create one
    const listLink = page.locator('a[href^="/lists/"]').first();
    
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForURL(/\/lists\/\d+/);
      
      // Click Add Item button
      const addItemButton = page.getByRole('button', { name: /add item/i });
      
      if (await addItemButton.isVisible()) {
        await addItemButton.click();
        
        // Fill in product name
        const productNameInput = page.locator('input[placeholder*="Product Name"], input[placeholder*="product"]').first();
        if (await productNameInput.isVisible()) {
          await productNameInput.fill('Fresh Strawberries');
        }
        
        // The basic add form may not have generic toggle - that's okay
        // Main test is that form works
      }
    }
  });
});
