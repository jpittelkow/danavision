<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SSOTestService
{
    private const GROUP = 'sso';

    private const ALLOWED_PROVIDERS = ['google', 'github', 'microsoft', 'apple', 'discord', 'gitlab', 'oidc'];

    private const DISCOVERY_URLS = [
        'google' => 'https://accounts.google.com/.well-known/openid-configuration',
        'microsoft' => 'https://login.microsoftonline.com/common/v2.0/.well-known/openid-configuration',
    ];

    private const TOKEN_ENDPOINTS = [
        'github' => 'https://github.com/login/oauth/access_token',
        'discord' => 'https://discord.com/api/oauth2/token',
        'gitlab' => 'https://gitlab.com/oauth/token',
    ];

    public function __construct(
        private SettingService $settingService,
        private UrlValidationService $urlValidator
    ) {}

    /**
     * Test SSO provider configuration by validating credentials at the provider's token endpoint.
     *
     * @return array{success: bool, data?: array, error?: string, status?: int}
     */
    public function testProvider(string $provider, int $userId): array
    {
        $provider = strtolower($provider);

        if (!in_array($provider, self::ALLOWED_PROVIDERS, true)) {
            return $this->error('Unknown provider. Use: ' . implode(', ', self::ALLOWED_PROVIDERS), 422);
        }

        $settings = $this->settingService->getGroup(self::GROUP);
        $redirectUri = rtrim(config('app.url'), '/') . '/api/auth/callback/' . $provider;

        if ($provider === 'oidc') {
            return $this->testOidcProvider($settings, $redirectUri, $userId);
        }

        $clientIdKey = $provider . '_client_id';
        $clientSecretKey = $provider . '_client_secret';
        $clientId = $settings[$clientIdKey] ?? config('services.' . $provider . '.client_id');
        $clientSecret = $settings[$clientSecretKey] ?? config('services.' . $provider . '.client_secret');

        if (empty($clientId)) {
            return $this->error('Client ID is not set. Add credentials first.');
        }
        if (empty($clientSecret)) {
            return $this->error('Client Secret is not set. Add credentials to validate.', 422);
        }

        if (isset(self::DISCOVERY_URLS[$provider])) {
            return $this->testDiscoveryProvider($provider, $clientId, $clientSecret, $redirectUri, $userId);
        }

        if (isset(self::TOKEN_ENDPOINTS[$provider])) {
            return $this->testTokenEndpointProvider($provider, $clientId, $clientSecret, $redirectUri, $userId);
        }

        if ($provider === 'apple') {
            return $this->error('Apple credential validation is not supported. Configure redirect URI and test sign-in manually.');
        }

        return $this->error('Unknown provider.');
    }

    /**
     * Test OIDC provider credentials via discovery endpoint.
     */
    private function testOidcProvider(array $settings, string $redirectUri, int $userId): array
    {
        $issuerUrl = $settings['oidc_issuer_url'] ?? config('services.oidc.issuer_url');
        $clientId = $settings['oidc_client_id'] ?? config('services.oidc.client_id');
        $clientSecret = $settings['oidc_client_secret'] ?? config('services.oidc.client_secret');

        if (empty($issuerUrl) || empty($clientId)) {
            return $this->error('OIDC requires Issuer URL and Client ID to be set.', 422);
        }
        if (empty($clientSecret)) {
            return $this->error('OIDC requires Client Secret to be set to validate credentials.', 422);
        }

        $discoveryUrl = rtrim($issuerUrl, '/') . '/.well-known/openid-configuration';

        if (!$this->urlValidator->validateUrl($discoveryUrl)) {
            return $this->error('Invalid OIDC Issuer URL: URLs pointing to internal or private addresses are not allowed.');
        }

        try {
            $response = Http::timeout(10)->get($discoveryUrl);
            if (!$response->successful()) {
                return $this->error(
                    'Could not reach OIDC discovery endpoint: ' . $response->status() . ' ' . $response->reason()
                );
            }

            $body = $response->json();
            if (empty($body['issuer'] ?? null)) {
                return $this->error('Discovery response missing issuer.');
            }

            $tokenEndpoint = $body['token_endpoint'] ?? null;
            if (empty($tokenEndpoint)) {
                return $this->error('Discovery response missing token_endpoint.');
            }

            if (!$this->validateCredentialsAtTokenEndpoint($tokenEndpoint, $clientId, $clientSecret, $redirectUri)) {
                return $this->error('Invalid OIDC client credentials. Check Client ID and Client Secret.');
            }

            $this->settingService->set(self::GROUP, 'oidc_test_passed', true, $userId);

            return $this->success(['message' => 'OIDC credentials validated successfully', 'issuer' => $body['issuer']]);
        } catch (\Throwable $e) {
            Log::warning('SSO OIDC test failed', ['provider' => 'oidc', 'error' => $e->getMessage()]);

            return $this->error('Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Test providers that expose an OpenID discovery endpoint (Google, Microsoft).
     */
    private function testDiscoveryProvider(
        string $provider,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        int $userId
    ): array {
        try {
            $response = Http::timeout(10)->get(self::DISCOVERY_URLS[$provider]);
            if (!$response->successful()) {
                return $this->error(
                    'Could not reach provider discovery: ' . $response->status() . ' ' . $response->reason()
                );
            }

            $body = $response->json();
            $tokenEndpoint = $body['token_endpoint'] ?? null;
            if (empty($tokenEndpoint)) {
                return $this->error('Discovery response missing token_endpoint.');
            }

            if (!$this->validateCredentialsAtTokenEndpoint($tokenEndpoint, $clientId, $clientSecret, $redirectUri)) {
                return $this->error('Invalid credentials. Check Client ID and Client Secret.');
            }

            $this->settingService->set(self::GROUP, $provider . '_test_passed', true, $userId);

            return $this->success(['message' => 'Credentials validated successfully.']);
        } catch (\Throwable $e) {
            Log::warning('SSO provider test failed', ['provider' => $provider, 'error' => $e->getMessage()]);

            return $this->error('Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Test providers with known token endpoints (GitHub, Discord, GitLab).
     */
    private function testTokenEndpointProvider(
        string $provider,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        int $userId
    ): array {
        $tokenUrl = self::TOKEN_ENDPOINTS[$provider];
        $useJson = $provider === 'github';

        if (!$this->validateCredentialsAtTokenEndpoint($tokenUrl, $clientId, $clientSecret, $redirectUri, $useJson)) {
            return $this->error('Invalid credentials. Check Client ID and Client Secret.');
        }

        $this->settingService->set(self::GROUP, $provider . '_test_passed', true, $userId);

        return $this->success(['message' => 'Credentials validated successfully.']);
    }

    /**
     * Attempt token exchange with a fake auth code.
     * Invalid client = bad credentials; invalid_grant = credentials OK.
     */
    private function validateCredentialsAtTokenEndpoint(
        string $tokenUrl,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        bool $useJsonRequest = false
    ): bool {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => 'test_connection_validation',
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        try {
            if ($useJsonRequest) {
                $response = Http::timeout(10)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->asForm()
                    ->post($tokenUrl, $params);
            } else {
                $response = Http::timeout(10)->asForm()->post($tokenUrl, $params);
            }

            $status = $response->status();
            $body = $response->json() ?? [];
            $error = $body['error'] ?? $body['error_description'] ?? '';

            // 401 or invalid_client => credentials rejected
            if ($status === 401) {
                return false;
            }
            if (is_string($error) && strtolower($error) === 'invalid_client') {
                return false;
            }

            // invalid_grant, invalid_request, bad_verification_code (GitHub) => credentials accepted, request/code invalid (expected)
            $acceptedErrors = ['invalid_grant', 'invalid_request', 'bad_verification_code'];
            if (is_string($error) && in_array(strtolower($error), $acceptedErrors, true)) {
                return true;
            }

            // incorrect_client_credentials (GitHub) => wrong credentials
            if (is_string($error) && stripos($error, 'incorrect_client') !== false) {
                return false;
            }

            // redirect_uri_mismatch => configuration issue, not valid for test
            if (is_string($error) && strtolower($error) === 'redirect_uri_mismatch') {
                return false;
            }

            // Unknown response: treat as failure for safety
            if ($status >= 400) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::debug('SSO token endpoint validation failed', ['url' => $tokenUrl, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Build a success result array.
     */
    private function success(array $data): array
    {
        return ['success' => true, 'data' => $data];
    }

    /**
     * Build an error result array.
     */
    private function error(string $message, int $status = 422): array
    {
        return ['success' => false, 'error' => $message, 'status' => $status];
    }
}
