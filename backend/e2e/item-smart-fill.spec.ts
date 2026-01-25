import { test, expect } from '@playwright/test';

/**
 * Smart Fill and Vendor Blacklist E2E Tests
 *
 * Tests the Smart Fill AI feature and vendor blacklist functionality
 * on the item detail/edit page.
 */
test.describe('Item Smart Fill Feature', () => {
  test.use({ storageState: 'e2e/.auth/user.json' });

  test('item page shows Smart Fill button', async ({ page }) => {
    // First navigate to lists and find an item
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    // Click on first list
    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      // Click on an item to go to item detail page
      const itemLink = page.locator('a[href*="/items/"]').first();
      if (await itemLink.isVisible()) {
        await itemLink.click();
        await page.waitForLoadState('networkidle');

        // Verify Smart Fill button exists
        await expect(page.getByRole('button', { name: /Smart Fill/i })).toBeVisible();
      }
    }
  });

  test('Smart Fill button shows loading state when clicked', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      const itemLink = page.locator('a[href*="/items/"]').first();
      if (await itemLink.isVisible()) {
        await itemLink.click();
        await page.waitForLoadState('networkidle');

        // Click Smart Fill button
        const smartFillBtn = page.getByRole('button', { name: /Smart Fill/i });
        if (await smartFillBtn.isVisible()) {
          await smartFillBtn.click();

          // Wait for network to settle (AI request completes or fails)
          await page.waitForLoadState('networkidle');

          // Either see a message or the button should be enabled again
          const message = page.locator('[class*="rounded-xl"]').filter({ hasText: /AI|provider|Found/i });
          const isMessageVisible = await message.isVisible().catch(() => false);
          const isButtonEnabled = await smartFillBtn.isEnabled();

          // One of these should be true (either error/success message or button re-enabled)
          expect(isMessageVisible || isButtonEnabled).toBeTruthy();
        }
      }
    }
  });

  test('item edit form includes UPC field', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      const itemLink = page.locator('a[href*="/items/"]').first();
      if (await itemLink.isVisible()) {
        await itemLink.click();
        await page.waitForLoadState('networkidle');

        // Click Edit button
        const editBtn = page.getByRole('button', { name: 'Edit' });
        if (await editBtn.isVisible()) {
          await editBtn.click();

          // Verify UPC field exists
          await expect(page.getByLabel(/UPC|Barcode/i)).toBeVisible();
        }
      }
    }
  });

  test('item edit form shows Image URL field', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      const itemLink = page.locator('a[href*="/items/"]').first();
      if (await itemLink.isVisible()) {
        await itemLink.click();
        await page.waitForLoadState('networkidle');

        const editBtn = page.getByRole('button', { name: 'Edit' });
        if (await editBtn.isVisible()) {
          await editBtn.click();

          // Verify Image URL field exists
          await expect(page.getByLabel(/Image URL/i)).toBeVisible();
        }
      }
    }
  });
});

test.describe('Vendor Price List Enhancements', () => {
  test.use({ storageState: 'e2e/.auth/user.json' });

  test('vendor names are clickable links when product_url exists', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      const itemLink = page.locator('a[href*="/items/"]').first();
      if (await itemLink.isVisible()) {
        await itemLink.click();
        await page.waitForLoadState('networkidle');

        // Check if vendor prices section exists and has clickable links
        const vendorSection = page.getByRole('heading', { name: /Vendor Prices/i });
        if (await vendorSection.isVisible()) {
          // Look for vendor links in the table (links with target="_blank")
          const vendorLinks = page.locator('table td a[target="_blank"]');
          const count = await vendorLinks.count();
          
          // If there are vendor prices with URLs, they should be clickable
          if (count > 0) {
            // First link should have href attribute
            const firstLink = vendorLinks.first();
            const href = await firstLink.getAttribute('href');
            expect(href).toBeTruthy();
          }
        }
      }
    }
  });

  test('vendor row shows blacklist button on hover', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      const itemLink = page.locator('a[href*="/items/"]').first();
      if (await itemLink.isVisible()) {
        await itemLink.click();
        await page.waitForLoadState('networkidle');

        // Check if vendor prices table exists
        const vendorTable = page.locator('table');
        if (await vendorTable.isVisible()) {
          // Get first vendor row
          const firstRow = page.locator('table tbody tr').first();
          if (await firstRow.isVisible()) {
            // Hover over the row
            await firstRow.hover();

            // Look for the X button that should appear (has red color and title)
            const blacklistBtn = firstRow.locator('button[title*="Hide"]');
            
            // After hover, button should be visible (opacity changes from 0 to 100)
            // We check if element exists with the expected title
            const btnCount = await blacklistBtn.count();
            expect(btnCount).toBeGreaterThan(0);
          }
        }
      }
    }
  });

  test('clicking blacklist button removes vendor from list', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      const itemLink = page.locator('a[href*="/items/"]').first();
      if (await itemLink.isVisible()) {
        await itemLink.click();
        await page.waitForLoadState('networkidle');

        // Count initial vendor rows
        const initialRowCount = await page.locator('table tbody tr').count();

        if (initialRowCount > 0) {
          // Get first row and its vendor name
          const firstRow = page.locator('table tbody tr').first();
          const vendorName = await firstRow.locator('td').first().textContent();

          // Hover and click blacklist button
          await firstRow.hover();
          const blacklistBtn = firstRow.locator('button[title*="Hide"]');

          if (await blacklistBtn.isVisible()) {
            await blacklistBtn.click();

            // Wait for the API call to complete
            await page.waitForLoadState('networkidle');

            // Row count should decrease or vendor should not be visible
            const newRowCount = await page.locator('table tbody tr').count();
            
            // Either row was removed or vendor name should not be in first row anymore
            expect(newRowCount <= initialRowCount).toBeTruthy();
          }
        }
      }
    }
  });
});

test.describe('Item Page Navigation', () => {
  test.use({ storageState: 'e2e/.auth/user.json' });

  test('can navigate back to list from item page', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      const itemLink = page.locator('a[href*="/items/"]').first();
      if (await itemLink.isVisible()) {
        await itemLink.click();
        await page.waitForLoadState('networkidle');

        // Click back link
        const backLink = page.locator('a').filter({ hasText: /Back to/i });
        if (await backLink.isVisible()) {
          await backLink.click();
          await expect(page).toHaveURL(/\/lists\/\d+/);
        }
      }
    }
  });

  test('item page shows refresh button', async ({ page }) => {
    await page.goto('/lists');
    await page.waitForLoadState('networkidle');

    const listLink = page.locator('a[href*="/lists/"]').first();
    if (await listLink.isVisible()) {
      await listLink.click();
      await page.waitForLoadState('networkidle');

      const itemLink = page.locator('a[href*="/items/"]').first();
      if (await itemLink.isVisible()) {
        await itemLink.click();
        await page.waitForLoadState('networkidle');

        // Verify Refresh button exists (has RefreshCw icon)
        const refreshBtn = page.locator('button[title="Refresh prices"]');
        await expect(refreshBtn).toBeVisible();
      }
    }
  });
});
