import { test, expect } from '@playwright/test';

/**
 * Shop Local Feature E2E Tests
 *
 * Tests the Shop Local toggle functionality on lists and items.
 */
test.describe('Shop Local Feature', () => {
  test.use({ storageState: 'e2e/.auth/user.json' });

  test('can view shop local toggle on list page', async ({ page }) => {
    await page.goto('/lists');
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

    if (!listLink) {
      test.skip(true, 'No lists available - skipping test');
      return;
    }

    await listLink.click();
    await page.waitForLoadState('networkidle');

    // Verify we're on a list detail page
    await expect(page).toHaveURL(/\/lists\/\d+/);

    await expect(page.getByText('Shop Local').first()).toBeVisible();
  });

  test('shop local toggle is present on list detail page', async ({ page }) => {
    // Navigate to create a list if needed
    await page.goto('/lists/create');
    await page.waitForLoadState('networkidle');

    // Fill in the form using the ID selector
    await page.fill('#name', `Test Shop Local List ${Date.now()}`);
    await page.getByRole('button', { name: /Create List/i }).click();

    // Wait for redirect
    await page.waitForLoadState('networkidle');

    // Should redirect to the list and see shop local toggle
    await expect(page.getByText('Shop Local').first()).toBeVisible();
  });

  test('can toggle shop local on list and state persists', async ({ page }) => {
    await page.goto('/lists/create');

    await page.getByLabel('Name').fill(`Toggle Persist Test List ${Date.now()}`);
    await page.getByRole('button', { name: /Create List/i }).click();

    // Wait for page to load
    await page.waitForURL(/\/lists\/\d+/);

    // Find the shop local switch - it's near the "Shop Local" text
    const shopLocalSwitch = page.locator('button[role="switch"]').first();

    // Initially should be unchecked
    await expect(shopLocalSwitch).toHaveAttribute('data-state', 'unchecked');

    // Click the switch
    await shopLocalSwitch.click();

    // Wait for the update to complete - flash message says "List updated successfully!"
    await expect(
      page.getByText(/updated successfully/i).or(page.getByText(/success/i))
    ).toBeVisible({ timeout: 10000 });

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

    await page.getByLabel('Name').fill(`Toggle Off Test List ${Date.now()}`);
    await page.getByRole('button', { name: /Create List/i }).click();

    await page.waitForURL(/\/lists\/\d+/);

    const shopLocalSwitch = page.locator('button[role="switch"]').first();

    // Enable shop local
    await shopLocalSwitch.click();
    await expect(
      page.getByText(/updated successfully/i).or(page.getByText(/success/i))
    ).toBeVisible({ timeout: 10000 });
    await expect(shopLocalSwitch).toHaveAttribute('data-state', 'checked');

    // Wait a moment for the UI to stabilize
    await page.waitForLoadState('networkidle');

    // Disable shop local
    await shopLocalSwitch.click();
    await expect(
      page.getByText(/updated successfully/i).or(page.getByText(/success/i))
    ).toBeVisible({ timeout: 10000 });
    await expect(shopLocalSwitch).toHaveAttribute('data-state', 'unchecked');
  });
});

test.describe('Shop Local on Items', () => {
  test.use({ storageState: 'e2e/.auth/user.json' });

  test('item detail page can be accessed from list', async ({ page }) => {
    // First create a list with an item
    await page.goto('/lists/create');
    await page.waitForLoadState('networkidle');

    await page.fill('#name', `Item Test List ${Date.now()}`);
    await page.getByRole('button', { name: /Create List/i }).click();
    await page.waitForLoadState('networkidle');

    // Add an item using Add Item button
    const addItemButton = page.getByRole('button', { name: /Add Item/i }).first();
    await expect(addItemButton).toBeVisible();
    await addItemButton.click();

    // Wait for form to appear
    await expect(page.locator('input[type="text"]').first()).toBeVisible();

    // Fill in the product name in the form
    const productInput = page.locator('input[type="text"]').first();
    await productInput.fill('Test Product');
    await page.locator('button[type="submit"]').click();

    // Wait for item to be added
    await page.waitForLoadState('networkidle');

    // Click on the item to view details
    const itemLink = page.locator('a:has-text("Test Product")');
    const itemCount = await itemLink.count();

    if (itemCount > 0) {
      await itemLink.first().click();
      await page.waitForLoadState('networkidle');

      // Should be on item detail page
      await expect(page).toHaveURL(/\/items\/\d+/);
    }
  });

  test('item page shows product information', async ({ page }) => {
    // Navigate to an existing item if available
    await page.goto('/items');
    await page.waitForLoadState('networkidle');

    // Click on the first item if available
    const itemLink = page.locator('a[href^="/items/"]').first();

    if (await itemLink.isVisible().catch(() => false)) {
      await itemLink.click();
      await page.waitForURL(/\/items\/\d+/);

      // Item page should show product name
      await expect(page.locator('h1, h2').first()).toBeVisible();
    } else {
      test.skip(true, 'No items available - skipping test');
    }
  });
});
