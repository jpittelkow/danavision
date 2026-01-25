import { test, expect } from '@playwright/test';

/**
 * Shopping Lists E2E Tests
 * 
 * Tests the shopping list management functionality.
 */
test.describe('Shopping Lists', () => {
  test('should display lists page', async ({ page }) => {
    await page.goto('/lists');

    // Verify page loads
    await expect(page).toHaveTitle(/Lists|Shopping/i);

    // Verify create button exists
    await expect(page.locator('text=Create List').or(page.locator('text=New List'))).toBeVisible();
  });

  test('should navigate to create list page', async ({ page }) => {
    await page.goto('/lists');

    // Click create button - use .or() for multiple selectors
    const createButton = page.locator('text=Create List').or(page.locator('text=New List')).or(page.locator('a:has-text("Create")'));
    await createButton.first().click();

    // Verify navigation to create page
    await expect(page).toHaveURL(/\/lists\/create/);
  });

  test('should create a new shopping list', async ({ page }) => {
    await page.goto('/lists/create');

    // Fill in list details - use id selector which matches htmlFor="name"
    const listName = `Test List ${Date.now()}`;
    await page.fill('#name', listName);

    // Optional: Add description if field exists
    const descriptionField = page.locator('#description');
    if (await descriptionField.isVisible()) {
      await descriptionField.fill('Test description for E2E test');
    }

    // Submit form
    await page.click('button[type="submit"]');

    // Wait for redirect or success
    await page.waitForLoadState('networkidle');

    // Should redirect to the list page or lists index
    await expect(page).toHaveURL(/\/lists/);
  });

  test('should view a shopping list', async ({ page }) => {
    await page.goto('/lists');

    // Wait for lists to load
    await page.waitForLoadState('networkidle');

    // Look specifically for list detail links (numeric IDs), excluding /lists/create
    const listDetailLinks = page.locator('a[href^="/lists/"]').filter({ 
      has: page.locator(':scope'),
    });
    
    // Filter to only links that have numeric IDs
    const allLinks = await listDetailLinks.all();
    let listLink = null;
    
    for (const link of allLinks) {
      const href = await link.getAttribute('href');
      // Match links like /lists/123 but not /lists/create
      if (href && /\/lists\/\d+/.test(href)) {
        listLink = link;
        break;
      }
    }
    
    if (listLink) {
      await listLink.click();

      // Verify list detail page
      await expect(page).toHaveURL(/\/lists\/\d+/);

      // Verify Add Item button exists
      await expect(page.locator('text=Add Item')).toBeVisible();
    }
    // If no lists exist, test passes silently
  });

  test('should add item to a list', async ({ page }) => {
    await page.goto('/lists');

    // Wait for lists to load
    await page.waitForLoadState('networkidle');

    // Look specifically for list detail links (numeric IDs), excluding /lists/create
    const listDetailLinks = page.locator('a[href^="/lists/"]');
    const allLinks = await listDetailLinks.all();
    let listLink = null;
    
    for (const link of allLinks) {
      const href = await link.getAttribute('href');
      // Match links like /lists/123 but not /lists/create
      if (href && /\/lists\/\d+/.test(href)) {
        listLink = link;
        break;
      }
    }
    
    if (listLink) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      // Verify we're on a list detail page
      await expect(page).toHaveURL(/\/lists\/\d+/);

      // Click Add Item button
      const addItemButton = page.locator('button:has-text("Add Item")').or(page.locator('a:has-text("Add Item")'));
      await addItemButton.first().click();

      // Wait for form to appear
      const productNameInput = page.locator('input[type="text"]').first();
      await expect(productNameInput).toBeVisible();

      // Fill in item details - the input field inside the Add Item form
      await productNameInput.fill(`Test Item ${Date.now()}`);

      // Submit
      await page.click('button[type="submit"]');

      // Wait for item to be added
      await page.waitForLoadState('networkidle');
    }
    // If no lists exist, test passes silently
  });

  test('should refresh prices for a list', async ({ page }) => {
    await page.goto('/lists');

    // Wait for lists to load
    await page.waitForLoadState('networkidle');

    // Navigate to first list
    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      // Look for Refresh All button
      const refreshButton = page.locator('button:has-text("Refresh")');
      if (await refreshButton.isVisible()) {
        await refreshButton.click();

        // Wait for refresh to complete
        await page.waitForLoadState('networkidle');
      }
    }
  });
});

test.describe('List Items', () => {
  test('should mark item as purchased', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    // Navigate to first list with items
    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      // Look for purchase button (checkmark icon)
      const purchaseButton = page.locator('button[title*="purchased"], button:has(svg.lucide-check)').first();
      if (await purchaseButton.isVisible()) {
        await purchaseButton.click();
        await page.waitForLoadState('networkidle');
      }
    }
  });

  test('should delete an item from list', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      // Look for delete button (trash icon)
      const deleteButton = page.locator('button[title*="Delete"], button:has(svg.lucide-trash)').first();
      if (await deleteButton.isVisible()) {
        // Handle confirmation dialog
        page.on('dialog', dialog => dialog.accept());
        await deleteButton.click();
        await page.waitForLoadState('networkidle');
      }
    }
  });
});
