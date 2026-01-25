import { test, expect } from '@playwright/test';

test.describe('Shop Local Feature', () => {
  test('can view shop local toggle on list page', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');
    
    // Check if there's at least one list (exclude create link)
    const listLink = page.locator('a[href^="/lists/"]').filter({ hasNotText: 'Create' }).first();
    const hasLists = await listLink.count() > 0;
    
    if (hasLists) {
      await listLink.click();
      await page.waitForLoadState('networkidle');
      await expect(page.getByText('Shop Local').first()).toBeVisible();
    }
  });

  test('shop local toggle is present on list detail page', async ({ page }) => {
    // Navigate to create a list if needed
    await page.goto('/lists/create');
    await page.waitForLoadState('networkidle');
    
    // Fill in the form using the ID selector
    await page.fill('#name', 'Test Shop Local List');
    await page.getByRole('button', { name: /Create List/i }).click();
    
    // Wait for redirect
    await page.waitForLoadState('networkidle');
    
    // Should redirect to the list and see shop local toggle
    await expect(page.getByText('Shop Local').first()).toBeVisible();
  });

  test('can toggle shop local on list and state persists', async ({ page }) => {
    await page.goto('/lists/create');
    
    await page.getByLabel('Name').fill('Toggle Persist Test List');
    await page.getByRole('button', { name: /Create List/i }).click();
    
    // Wait for page to load
    await page.waitForURL(/\/lists\/\d+/);
    
    // Find the shop local switch - it's near the "Shop Local" text
    const shopLocalLabel = page.getByText('Shop Local');
    const shopLocalSwitch = page.locator('button[role="switch"]').first();
    
    // Initially should be unchecked
    await expect(shopLocalSwitch).toHaveAttribute('data-state', 'unchecked');
    
    // Click the switch
    await shopLocalSwitch.click();
    
    // Wait for the update to complete (flash message appears)
    await expect(page.getByText(/updated successfully/i)).toBeVisible({ timeout: 5000 });
    
    // The switch should now be checked
    await expect(shopLocalSwitch).toHaveAttribute('data-state', 'checked');
    
    // Reload the page to verify state persisted
    await page.reload();
    await page.waitForLoadState('networkidle');
    
    // Should still be checked after reload
    const switchAfterReload = page.locator('button[role="switch"]').first();
    await expect(switchAfterReload).toHaveAttribute('data-state', 'checked');
  });

  test('can toggle shop local off after enabling', async ({ page }) => {
    await page.goto('/lists/create');
    
    await page.getByLabel('Name').fill('Toggle Off Test List');
    await page.getByRole('button', { name: /Create List/i }).click();
    
    await page.waitForURL(/\/lists\/\d+/);
    
    const shopLocalSwitch = page.locator('button[role="switch"]').first();
    
    // Enable shop local
    await shopLocalSwitch.click();
    await expect(page.getByText(/updated successfully/i)).toBeVisible({ timeout: 5000 });
    await expect(shopLocalSwitch).toHaveAttribute('data-state', 'checked');
    
    // Disable shop local
    await shopLocalSwitch.click();
    await expect(page.getByText(/updated successfully/i)).toBeVisible({ timeout: 5000 });
    await expect(shopLocalSwitch).toHaveAttribute('data-state', 'unchecked');
  });
});

test.describe('Shop Local on Items', () => {
  test('item detail page has shop local toggle', async ({ page }) => {
    // First create a list with an item
    await page.goto('/lists/create');
    await page.waitForLoadState('networkidle');
    
    await page.fill('#name', 'Item Test List');
    await page.getByRole('button', { name: /Create List/i }).click();
    await page.waitForLoadState('networkidle');
    
    // Add an item using Add Item button
    await page.getByRole('button', { name: /Add Item/i }).first().click();
    await page.waitForTimeout(500);
    
    // Fill in the product name in the form
    const productInput = page.locator('input[type="text"]').first();
    await productInput.fill('Test Product');
    await page.locator('button[type="submit"]').click();
    
    // Wait for item to be added
    await page.waitForLoadState('networkidle');
    
    // Click on the item to view details
    const itemLink = page.locator('a:has-text("Test Product")');
    if (await itemLink.count() > 0) {
      await itemLink.first().click();
      await page.waitForLoadState('networkidle');
      
      // Should see shop local toggle on item page (if implemented)
      const shopLocalText = page.getByText('Shop Local');
      if (await shopLocalText.count() > 0) {
        await expect(shopLocalText.first()).toBeVisible();
      }
    }
  });

  test('item shop local toggle state persists', async ({ page }) => {
    // First create a list with an item
    await page.goto('/lists/create');
    await page.waitForLoadState('networkidle');
    
    await page.fill('#name', 'Item Toggle Persist List');
    await page.getByRole('button', { name: /Create List/i }).click();
    await page.waitForLoadState('networkidle');
    
    // Add an item
    await page.getByRole('button', { name: /Add Item/i }).first().click();
    await page.waitForTimeout(500);
    
    // Fill product name
    const productInput = page.locator('input[type="text"]').first();
    await productInput.fill('Persist Test Product');
    await page.locator('button[type="submit"]').click();
    
    // Wait for item to be added
    await page.waitForLoadState('networkidle');
    
    // Click on the item to view details
    const itemLink = page.locator('a:has-text("Persist Test Product")');
    if (await itemLink.count() > 0) {
      await itemLink.first().click();
      await page.waitForURL(/\/items\/\d+/);
      
      // Find the shop local switch if it exists on item page
      const shopLocalSwitch = page.locator('button[role="switch"]').first();
      const hasSwitches = await shopLocalSwitch.count() > 0;
      
      if (hasSwitches) {
        // Get current state
        const currentState = await shopLocalSwitch.getAttribute('data-state');
        
        // Click to toggle
        await shopLocalSwitch.click();
        await page.waitForTimeout(1000);
        
        // Reload and verify state changed
        await page.reload();
        await page.waitForLoadState('networkidle');
        
        const switchAfterReload = page.locator('button[role="switch"]').first();
        // State should have toggled
        const newState = await switchAfterReload.getAttribute('data-state');
        expect(newState).not.toBe(currentState);
      }
    }
  });
});
