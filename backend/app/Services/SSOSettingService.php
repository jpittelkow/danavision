<?php

namespace App\Services;

class SSOSettingService
{
    private const OAUTH_PROVIDERS = ['google', 'github', 'microsoft', 'apple', 'discord', 'gitlab'];

    /**
     * Enforce provider credential requirements, test-pass requirements,
     * and clear test_passed when credentials change.
     *
     * Mutates $validated in place.
     */
    public function enforceProviderRules(array &$validated, array $oldSettings): void
    {
        $this->enforceCredentialRequirements($validated, $oldSettings);
        $this->enforceTestPassRequirements($validated, $oldSettings);
        $this->clearTestOnCredentialChange($validated, $oldSettings);
    }

    /**
     * Disable providers that are missing required credentials.
     */
    private function enforceCredentialRequirements(array &$validated, array $oldSettings): void
    {
        foreach (self::OAUTH_PROVIDERS as $provider) {
            $clientId = $validated[$provider . '_client_id'] ?? $oldSettings[$provider . '_client_id'] ?? '';
            if (trim((string) $clientId) === '') {
                $validated[$provider . '_enabled'] = false;
            }
        }

        $oidcClientId = trim((string) ($validated['oidc_client_id'] ?? $oldSettings['oidc_client_id'] ?? ''));
        $oidcIssuerUrl = trim((string) ($validated['oidc_issuer_url'] ?? $oldSettings['oidc_issuer_url'] ?? ''));
        if ($oidcClientId === '' || $oidcIssuerUrl === '') {
            $validated['oidc_enabled'] = false;
        }
    }

    /**
     * Disable providers that haven't passed a connection test.
     */
    private function enforceTestPassRequirements(array &$validated, array $oldSettings): void
    {
        foreach (self::OAUTH_PROVIDERS as $provider) {
            if (!empty($validated[$provider . '_enabled'])) {
                $testPassed = $oldSettings[$provider . '_test_passed'] ?? false;
                if (!$testPassed) {
                    $validated[$provider . '_enabled'] = false;
                }
            }
        }

        if (!empty($validated['oidc_enabled'])) {
            $oidcTestPassed = $oldSettings['oidc_test_passed'] ?? false;
            if (!$oidcTestPassed) {
                $validated['oidc_enabled'] = false;
            }
        }
    }

    /**
     * Clear test_passed flag when credentials change so the provider must be re-tested.
     */
    private function clearTestOnCredentialChange(array &$validated, array $oldSettings): void
    {
        foreach (self::OAUTH_PROVIDERS as $provider) {
            $oldId = trim((string) ($oldSettings[$provider . '_client_id'] ?? ''));
            $newId = trim((string) ($validated[$provider . '_client_id'] ?? $oldId));
            if ($oldId !== $newId) {
                $validated[$provider . '_test_passed'] = false;
            }
        }

        $oldOidcId = trim((string) ($oldSettings['oidc_client_id'] ?? ''));
        $newOidcId = trim((string) ($validated['oidc_client_id'] ?? $oldOidcId));
        $oldOidcIssuer = trim((string) ($oldSettings['oidc_issuer_url'] ?? ''));
        $newOidcIssuer = trim((string) ($validated['oidc_issuer_url'] ?? $oldOidcIssuer));
        if ($oldOidcId !== $newOidcId || $oldOidcIssuer !== $newOidcIssuer) {
            $validated['oidc_test_passed'] = false;
        }
    }
}
