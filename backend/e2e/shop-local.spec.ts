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

  test('can toggle shop local on list', async ({ page }) => {
    await page.goto('/lists/create');
    
    await page.getByLabel('Name').fill('Toggle Test List');
    await page.getByRole('button', { name: /Create List/i }).click();
    
    // Find and click the shop local switch
    const shopLocalSwitch = page.locator('button[role="switch"]').filter({ hasText: '' });
    await shopLocalSwitch.click();
    
    // The switch should be checked (state=checked)
    await expect(shopLocalSwitch).toHaveAttribute('data-state', 'checked');
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
    
    // Click on the item to view details
    await page.getByText('Test Product').click();
    
    // Should see shop local toggle on item page
    await expect(page.getByText('Shop Local')).toBeVisible();
  });
});
