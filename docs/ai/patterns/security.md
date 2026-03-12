# Security Patterns

URL validation, webhook signatures, password validation, and OAuth state.

## URL Validation (SSRF Protection)

Use `UrlValidationService` for all external URL fetches:

```php
use App\Services\UrlValidationService;

public function __construct(private UrlValidationService $urlValidator) {}

if (!$this->urlValidator->validateUrl($validated['url'])) {
    return response()->json(['message' => 'Invalid URL: internal or private addresses are not allowed'], 422);
}

// Safe HTTP fetch with redirect validation
$content = $this->urlValidator->fetchContent($url, timeout: 10);
```

**Use for:** Webhook URLs, OIDC discovery, image URLs, any user-provided URL.

## Webhook Signature Verification

```php
$secret = config('services.sourdough.webhook_secret');
$timestamp = $request->header('X-Webhook-Timestamp');
$signature = $request->header('X-Webhook-Signature');
$payload = $request->getContent();

$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
if (!hash_equals($expected, $signature)) {
    return response()->json(['error' => 'Invalid signature'], 401);
}

// Prevent replay attacks
if (abs(time() - (int) $timestamp) > 300) {
    return response()->json(['error' => 'Request too old'], 401);
}
```

## Password Validation

Use `Password::defaults()` for all password fields:

```php
use Illuminate\Validation\Rules\Password;

$validated = $request->validate([
    'password' => ['required', 'string', Password::defaults()],
]);
```

Policy configured in `AppServiceProvider::boot()`: mixed case, numbers, symbols, compromised check in production.

## OAuth State Validation

```php
// Generate state on redirect
$stateToken = bin2hex(random_bytes(32));
session()->put("oauth_state:{$provider}", $stateToken);

// Validate on callback
$receivedState = $request->input('state');
$expectedState = session()->pull("oauth_state:{$provider}");
if (!$expectedState || !hash_equals($expectedState, $receivedState)) {
    return response()->json(['error' => 'Invalid state'], 400);
}
```

## Filename Validation (Path Traversal Prevention)

```php
private function validateFilename(string $filename): bool
{
    if (!preg_match('/^sourdough-backup-\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.zip$/', $filename)) {
        return false;
    }
    if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
        return false;
    }
    return true;
}
```

## Rate Limiting Sensitive Endpoints

Use the `rate.sensitive` middleware for security-sensitive routes (login, register, password reset, 2FA):

```php
// In backend/routes/api.php
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('rate.sensitive:register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('rate.sensitive:login');

Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])
    ->middleware('rate.sensitive:2fa');
```

The `rate.sensitive` middleware is registered in `bootstrap/app.php` as an alias for `App\Http\Middleware\RateLimitSensitive`. It accepts a named limiter parameter that maps to rate limiter definitions (e.g. `:login`, `:register`, `:password_reset`, `:2fa`).

**Use for:** Any endpoint where brute-force or enumeration attacks are a concern.

**Key files:** `backend/app/Http/Middleware/RateLimitSensitive.php`, `backend/bootstrap/app.php`, `backend/app/Services/UrlValidationService.php`, `backend/app/Http/Controllers/Api/BackupController.php`, `backend/app/Http/Controllers/Api/FileManagerController.php`

## APP_KEY and Encrypted Model Attributes

Laravel's `APP_KEY` is the master encryption key for the application. It is auto-generated on first Docker boot and persisted to `${DATA_DIR}/.app_key` on the data volume.

**What APP_KEY encrypts:**
- Session data and cookies
- Any model attribute with the `'encrypted'` cast (e.g. `AIProvider.api_key`, `Webhook.secret`)
- Backup settings exports (derived key from `APP_KEY` + user password)

**Using the `encrypted` cast on models:**

```php
// In your model — follow the AIProvider / Webhook pattern:
protected $hidden = ['api_key'];          // Never expose in JSON responses

protected function casts(): array
{
    return [
        'api_key' => 'encrypted',         // Transparent encrypt/decrypt via APP_KEY
    ];
}
```

**Best practices:**
- **Back up the `.app_key` file** separately from your database. If the data volume is lost and no backup exists, all encrypted attributes become unrecoverable.
- **Never commit APP_KEY** to version control. The `.env` file is gitignored; the `.app_key` file lives on the Docker data volume.
- **Key rotation** requires re-encrypting all encrypted model attributes. There is no built-in rotation command — you must decrypt with the old key and re-encrypt with the new one.
- **Use `$hidden`** on any model field with the `encrypted` cast to prevent it from appearing in API responses (JSON serialization).
- **Return secrets only on creation** — if a user needs to see a secret (e.g. webhook signing secret), return it in the `store()` response only, never in `index()` or `update()`.

**Key files:** `docker/entrypoint.sh` (auto-generation), `backend/app/Models/AIProvider.php` (reference pattern), `backend/app/Models/Webhook.php`

**Related:** [Anti-patterns: Architecture](../anti-patterns/architecture.md)

## Implementation Journal

- [Security Vulnerability Fixes (2026-02-01)](../../journal/2026-02-01-security-vulnerability-fixes.md)
- [SSO Redirect URI Fix (2026-02-05)](../../journal/2026-02-05-sso-redirect-uri-fix.md)
