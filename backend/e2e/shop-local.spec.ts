import { test, expect } from '@playwright/test';

test.describe('Shop Local Feature', () => {
  test.use({ storageState: 'e2e/.auth/user.json' });

  test('can view shop local toggle on list page', async ({ page }) => {
    await page.goto('/lists');
    
    // Check if there's at least one list, or create one
    const listLink = page.locator('a[href*="/lists/"]').first();
    const hasLists = await listLink.count() > 0;
    
    if (hasLists) {
      await listLink.click();
      await expect(page.getByText('Shop Local')).toBeVisible();
    }
  });

  test('shop local toggle is present on list detail page', async ({ page }) => {
    // Navigate to create a list if needed
    await page.goto('/lists/create');
    
    // Fill in the form
    await page.getByLabel('Name').fill('Test Shop Local List');
    await page.getByRole('button', { name: /Create List/i }).click();
    
    // Should redirect to the list and see shop local toggle
    await expect(page.getByText('Shop Local')).toBeVisible();
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
  test.use({ storageState: 'e2e/.auth/user.json' });

  test('item detail page has shop local toggle', async ({ page }) => {
    // First create a list with an item
    await page.goto('/lists/create');
    await page.getByLabel('Name').fill('Item Test List');
    await page.getByRole('button', { name: /Create List/i }).click();
    
    // Add an item using Add Item button
    await page.getByRole('button', { name: /Add Item/i }).click();
    await page.getByLabel('Product Name').fill('Test Product');
    await page.getByRole('button', { name: /Add Item/i }).last().click();
    
    // Wait for item to be added
    await page.waitForTimeout(1000);
    
    // Click on the item to view details
    await page.getByText('Test Product').click();
    
    // Should see shop local toggle on item page
    await expect(page.getByText('Shop Local')).toBeVisible();
  });

  test('item shop local toggle state persists', async ({ page }) => {
    // First create a list with an item
    await page.goto('/lists/create');
    await page.getByLabel('Name').fill('Item Toggle Persist List');
    await page.getByRole('button', { name: /Create List/i }).click();
    
    // Add an item
    await page.getByRole('button', { name: /Add Item/i }).click();
    await page.getByLabel('Product Name').fill('Persist Test Product');
    await page.getByRole('button', { name: /Add Item/i }).last().click();
    
    // Wait for item to be added
    await page.waitForTimeout(1000);
    
    // Click on the item to view details
    await page.getByText('Persist Test Product').click();
    await page.waitForURL(/\/items\/\d+/);
    
    // Find and toggle the shop local switch
    const shopLocalSwitch = page.locator('button[role="switch"]').first();
    
    // Initially should be unchecked (or inheriting from list)
    await expect(shopLocalSwitch).toHaveAttribute('data-state', 'unchecked');
    
    // Enable shop local
    await shopLocalSwitch.click();
    await expect(page.getByText(/updated successfully/i)).toBeVisible({ timeout: 5000 });
    
    // Should now be checked
    await expect(shopLocalSwitch).toHaveAttribute('data-state', 'checked');
    
    // Reload and verify state persisted
    await page.reload();
    await page.waitForLoadState('networkidle');
    
    const switchAfterReload = page.locator('button[role="switch"]').first();
    await expect(switchAfterReload).toHaveAttribute('data-state', 'checked');
  });
});
