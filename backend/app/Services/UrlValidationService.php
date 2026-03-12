<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * URL Validation Service for SSRF Protection.
 *
 * Validates URLs to prevent Server-Side Request Forgery (SSRF) attacks
 * by blocking requests to private IPs, localhost, and cloud metadata endpoints.
 */
class UrlValidationService
{
    /**
     * Private IP ranges in CIDR notation.
     */
    private const PRIVATE_IP_RANGES = [
        '10.0.0.0/8',       // Class A private
        '172.16.0.0/12',    // Class B private
        '192.168.0.0/16',   // Class C private
        '127.0.0.0/8',      // Loopback
        '169.254.0.0/16',   // Link-local (includes cloud metadata)
        '0.0.0.0/8',        // Current network
        '100.64.0.0/10',    // Shared address space (CGN)
        '192.0.0.0/24',     // IETF Protocol assignments
        '192.0.2.0/24',     // TEST-NET-1 documentation
        '198.51.100.0/24',  // TEST-NET-2 documentation
        '203.0.113.0/24',   // TEST-NET-3 documentation
        '224.0.0.0/4',      // Multicast
        '240.0.0.0/4',      // Reserved
        '255.255.255.255/32', // Broadcast
    ];

    /**
     * IPv6 private/reserved ranges.
     */
    private const PRIVATE_IPV6_RANGES = [
        '::1/128',          // Loopback
        'fc00::/7',         // Unique local addresses
        'fe80::/10',        // Link-local
        'ff00::/8',         // Multicast
        '::ffff:0:0/96',    // IPv4-mapped (requires IPv4 check too)
    ];

    /**
     * Blocked hostnames.
     */
    private const BLOCKED_HOSTS = [
        'localhost',
        'localhost.localdomain',
        'ip6-localhost',
        'ip6-loopback',
        'metadata.google.internal',
        'metadata.google',
        'metadata',
    ];

    /**
     * Allowed URL schemes.
     */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * Validate a URL for safe external access.
     *
     * @param string $url The URL to validate
     * @return bool True if URL is safe to fetch, false otherwise
     */
    public function validateUrl(string $url): bool
    {
        return $this->validateAndResolve($url) !== null;
    }

    /**
     * Validate a URL and return the resolved IP for DNS pinning.
     *
     * Use this instead of validateUrl() when you will make an HTTP request,
     * then pass the result to pinnedOptions() to prevent DNS rebinding attacks.
     *
     * @param string $url The URL to validate
     * @return array{url: string, host: string, ip: string, port: int}|null Resolved data, or null if validation fails
     */
    public function validateAndResolve(string $url): ?array
    {
        $parsed = parse_url($url);

        if ($parsed === false || empty($parsed['host'])) {
            return null;
        }

        // Check scheme
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            return null;
        }

        $host = strtolower($parsed['host']);

        // Check blocked hostnames
        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            return null;
        }

        // Resolve hostname to IP addresses
        $ips = $this->resolveHost($host);

        if (empty($ips)) {
            // Cannot resolve - might be DNS rebinding or invalid host
            return null;
        }

        // Check each resolved IP
        foreach ($ips as $ip) {
            if ($this->isPrivateOrReservedIp($ip)) {
                return null;
            }
        }

        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);

        return [
            'url' => $url,
            'host' => $host,
            'ip' => $ips[0],
            'port' => (int) $port,
        ];
    }

    /**
     * Build Guzzle/HTTP options that pin DNS to a pre-resolved IP.
     *
     * This prevents DNS rebinding attacks by ensuring the HTTP client
     * uses the same IP address that was validated, bypassing re-resolution.
     *
     * @param array{host: string, ip: string, port: int} $resolved Result from validateAndResolve()
     * @return array Guzzle request options with CURLOPT_RESOLVE
     */
    public function pinnedOptions(array $resolved): array
    {
        return [
            'curl' => [
                CURLOPT_RESOLVE => [
                    "{$resolved['host']}:{$resolved['port']}:{$resolved['ip']}",
                ],
            ],
        ];
    }

    /**
     * Safely fetch content from a URL after validation.
     *
     * Uses DNS pinning via CURLOPT_RESOLVE to prevent DNS rebinding attacks.
     *
     * @param string $url The URL to fetch
     * @param int $timeout Timeout in seconds (default 10)
     * @return string|null The content, or null if validation fails or fetch errors
     */
    public function fetchContent(string $url, int $timeout = 10): ?string
    {
        $resolved = $this->validateAndResolve($url);

        if ($resolved === null) {
            Log::warning('URL validation failed for SSRF protection', [
                'url' => $url,
            ]);
            return null;
        }

        try {
            $response = Http::timeout($timeout)
                ->withOptions(array_merge(
                    $this->pinnedOptions($resolved),
                    [
                        'allow_redirects' => [
                            'max' => 3,
                            'strict' => true,
                            'referer' => false,
                            'protocols' => ['http', 'https'],
                            'on_redirect' => function ($request, $response, $uri) {
                                // Validate redirect target for SSRF protection.
                                // Note: DNS pinning cannot be applied mid-redirect chain in Guzzle,
                                // so redirect targets are validated but not pinned. The TOCTOU window
                                // is very small (milliseconds) and requires attacker-controlled DNS.
                                $redirectUrl = (string) $uri;
                                if ($this->validateAndResolve($redirectUrl) === null) {
                                    throw new \RuntimeException('Redirect to unsafe URL blocked');
                                }
                            },
                        ],
                    ]
                ))
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning('URL fetch returned non-success status', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::warning('URL fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve a hostname to IP addresses.
     *
     * @param string $host The hostname to resolve
     * @return array List of IP addresses
     */
    private function resolveHost(string $host): array
    {
        // If it's already an IP, return it directly
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        // Get IPv4 addresses
        $records = @dns_get_record($host, DNS_A);
        if ($records !== false) {
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                }
            }
        }

        // Get IPv6 addresses
        $records = @dns_get_record($host, DNS_AAAA);
        if ($records !== false) {
            foreach ($records as $record) {
                if (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        // Fallback to gethostbyname for edge cases
        if (empty($ips)) {
            $ip = @gethostbyname($host);
            if ($ip !== $host) {
                $ips[] = $ip;
            }
        }

        return $ips;
    }

    /**
     * Check if an IP address is private or reserved.
     *
     * @param string $ip The IP address to check
     * @return bool True if the IP is private/reserved
     */
    private function isPrivateOrReservedIp(string $ip): bool
    {
        // Use PHP's built-in validation for common cases
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        // Additional checks for cloud metadata endpoint
        // AWS/Azure/GCP metadata at 169.254.169.254
        if ($ip === '169.254.169.254') {
            return true;
        }

        // Check IPv4 ranges manually for comprehensive coverage
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            foreach (self::PRIVATE_IP_RANGES as $range) {
                if ($this->ipInCidr($ip, $range)) {
                    return true;
                }
            }
        }

        // Check IPv6 ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            foreach (self::PRIVATE_IPV6_RANGES as $range) {
                if ($this->ipv6InCidr($ip, $range)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if an IPv4 address is within a CIDR range.
     *
     * @param string $ip The IP address
     * @param string $cidr The CIDR range (e.g., "192.168.0.0/16")
     * @return bool True if IP is in range
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        $mask = (int) $mask;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - $mask);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Check if an IPv6 address is within a CIDR range.
     *
     * @param string $ip The IPv6 address
     * @param string $cidr The CIDR range
     * @return bool True if IP is in range
     */
    private function ipv6InCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);
        $mask = (int) $mask;

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        // Build mask
        $maskBin = str_repeat("\xff", intdiv($mask, 8));
        if ($mask % 8 !== 0) {
            $maskBin .= chr(256 - (1 << (8 - ($mask % 8))));
        }
        $maskBin = str_pad($maskBin, 16, "\x00");

        return ($ipBin & $maskBin) === ($subnetBin & $maskBin);
    }
}
