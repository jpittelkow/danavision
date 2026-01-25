<?php

namespace App\Traits;

use App\Models\Setting;

/**
 * SuppressesVendors Trait
 *
 * Provides shared functionality for filtering out suppressed (blacklisted) vendors
 * from price results. Used by controllers that display vendor price data.
 *
 * Suppressed vendors are stored per-user in the settings table and allow users
 * to hide results from vendors they don't want to see (e.g., vendors they don't
 * use, have had bad experiences with, etc.).
 */
trait SuppressesVendors
{
    /**
     * Get the list of suppressed vendors for a user.
     *
     * @param int $userId The user ID
     * @return array Array of suppressed vendor names (lowercase for matching)
     */
    protected function getSuppressedVendors(int $userId): array
    {
        $suppressedJson = Setting::get(Setting::SUPPRESSED_VENDORS, $userId);
        
        if (!$suppressedJson) {
            return [];
        }

        $decoded = json_decode($suppressedJson, true);
        
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Check if a vendor is suppressed (blacklisted) by the user.
     *
     * Uses partial matching - if "amazon" is suppressed, both "Amazon" and
     * "Amazon Fresh" would be suppressed. Similarly, suppressing "Amazon Fresh"
     * would also suppress "Amazon" due to bidirectional partial matching.
     *
     * @param string $vendor The vendor name to check
     * @param array $suppressedVendors List of suppressed vendor names
     * @return bool True if the vendor is suppressed, false otherwise
     */
    protected function isVendorSuppressed(string $vendor, array $suppressedVendors): bool
    {
        if (empty($suppressedVendors)) {
            return false;
        }

        $vendorLower = strtolower($vendor);
        
        foreach ($suppressedVendors as $suppressed) {
            $suppressedLower = strtolower($suppressed);
            
            // Bidirectional partial matching
            if (str_contains($vendorLower, $suppressedLower) || str_contains($suppressedLower, $vendorLower)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Filter a collection of vendor prices to exclude suppressed vendors.
     *
     * @param \Illuminate\Support\Collection $vendorPrices Collection of vendor price objects
     * @param int $userId The user ID to get suppression list for
     * @return \Illuminate\Support\Collection Filtered collection
     */
    protected function filterSuppressedVendorPrices($vendorPrices, int $userId)
    {
        $suppressedVendors = $this->getSuppressedVendors($userId);
        
        if (empty($suppressedVendors)) {
            return $vendorPrices;
        }

        return $vendorPrices->filter(function ($vp) use ($suppressedVendors) {
            return !$this->isVendorSuppressed($vp->vendor, $suppressedVendors);
        });
    }

    /**
     * Filter price history records to exclude suppressed retailers.
     *
     * @param \Illuminate\Support\Collection $priceHistory Collection of price history records
     * @param int $userId The user ID to get suppression list for
     * @return \Illuminate\Support\Collection Filtered collection
     */
    protected function filterSuppressedPriceHistory($priceHistory, int $userId)
    {
        $suppressedVendors = $this->getSuppressedVendors($userId);
        
        if (empty($suppressedVendors)) {
            return $priceHistory;
        }

        return $priceHistory->filter(function ($ph) use ($suppressedVendors) {
            return !$this->isVendorSuppressed($ph->retailer, $suppressedVendors);
        });
    }
}
