<?php

namespace App\Services\Notifications\Channels;

/**
 * Shared phone number country extraction for SMS channels.
 *
 * Provides basic country detection from E.164 phone number prefixes.
 * Used by TwilioChannel, VonageChannel, and SNSChannel for usage tracking metadata.
 */
trait ExtractsCountryFromPhone
{
    /**
     * Extract country code prefix from a phone number.
     * Requires '+' prefix for reliable detection.
     */
    private function extractCountryFromPhone(string $phone): ?string
    {
        $digits = preg_replace('/[^0-9+]/', '', $phone);
        if (str_starts_with($digits, '+1')) {
            return 'US';
        }
        if (str_starts_with($digits, '+44')) {
            return 'GB';
        }
        if (str_starts_with($digits, '+')) {
            return substr($digits, 0, 4);
        }

        return null;
    }
}
