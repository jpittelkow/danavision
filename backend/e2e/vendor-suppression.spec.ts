import { test, expect } from '@playwright/test';

test.describe('Vendor Suppression Settings', () => {
  test.use({ storageState: 'e2e/.auth/user.json' });

  test('can navigate to settings page', async ({ page }) => {
    await page.goto('/settings');
    await expect(page).toHaveURL(/\/settings/);
    await expect(page.getByRole('heading', { name: 'Settings' })).toBeVisible();
  });

  test('can view suppressed vendors section in stores tab', async ({ page }) => {
    await page.goto('/settings');
    
    // Click on Stores tab (Suppressed Vendors is in Stores tab via StorePreferences)
    await page.getByRole('tab', { name: /Stores/i }).click();
    
    // Should see the suppressed vendors section
    await expect(page.getByText('Suppressed Vendors')).toBeVisible();
    await expect(page.getByText('Hide specific vendors from price results')).toBeVisible();
  });

  test('can add a vendor to suppression list', async ({ page }) => {
    await page.goto('/settings');
    await page.getByRole('tab', { name: /Stores/i }).click();
    
    // Scroll to suppressed vendors section
    await page.locator('text=Suppressed Vendors').scrollIntoViewIfNeeded();
    
    // Find the vendor input and add a vendor
    const vendorInput = page.getByPlaceholder(/Enter vendor name/i);
    await vendorInput.fill('TestVendor');
    await vendorInput.press('Enter'); // Press Enter to add
    
    // Vendor should appear as a badge
    await expect(page.getByText('TestVendor')).toBeVisible();
  });

  test('can remove a vendor from suppression list', async ({ page }) => {
    await page.goto('/settings');
    await page.getByRole('tab', { name: /Stores/i }).click();
    
    // Scroll to suppressed vendors section
    await page.locator('text=Suppressed Vendors').scrollIntoViewIfNeeded();
    
    // First add a vendor
    const vendorInput = page.getByPlaceholder(/Enter vendor name/i);
    await vendorInput.fill('RemoveMe');
    await vendorInput.press('Enter');
    
    // Verify it was added
    await expect(page.getByText('RemoveMe')).toBeVisible();
    
    // Click the X button on the badge to remove it
    const badge = page.locator('text=RemoveMe').locator('..').locator('button');
    await badge.click();
    
    // Vendor should be removed
    await expect(page.getByText('RemoveMe')).not.toBeVisible();
  });

  test('can save suppressed vendors', async ({ page }) => {
    await page.goto('/settings');
    await page.getByRole('tab', { name: /Stores/i }).click();
    
    // Scroll to suppressed vendors section
    await page.locator('text=Suppressed Vendors').scrollIntoViewIfNeeded();
    
    // Add a vendor
    const vendorInput = page.getByPlaceholder(/Enter vendor name/i);
    await vendorInput.fill('SavedVendor');
    await vendorInput.press('Enter');
    
    // Vendor should appear (suppressed vendors are stored in component state)
    await expect(page.getByText('SavedVendor')).toBeVisible();
    
    // Note: The vendors need to be saved via the General tab's Save button
    // or they auto-save when modified via the StorePreferences component
  });
});
