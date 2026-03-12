# Service Pattern

Business logic goes in Services, not controllers. Controllers validate and route; services process.

## Service Class

```php
<?php
namespace App\Services\Example;

use App\Models\Example;
use Illuminate\Support\Facades\Log;

class ExampleService
{
    public function process(Example $example, array $options = []): ProcessResult
    {
        try {
            $result = $this->doSomething($example, $options);

            Log::info('Example processed', [
                'example_id' => $example->id,
                'result' => $result,
            ]);

            return new ProcessResult(success: true, data: $result);
        } catch (\Exception $e) {
            Log::error('Example processing failed', [
                'example_id' => $example->id,
                'error' => $e->getMessage(),
            ]);

            return new ProcessResult(success: false, error: $e->getMessage());
        }
    }
}
```

## Channel/Provider Pattern

Notification channels implement `ChannelInterface` and receive user-specific config from user settings:

```php
<?php
namespace App\Services\Notifications\Channels;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExampleChannel implements ChannelInterface
{
    public function send(User $user, string $type, string $title, string $message, array $data = []): array
    {
        $webhookUrl = $user->getSetting('notifications', 'example_webhook_url');

        if (!$webhookUrl) {
            throw new \RuntimeException('Example webhook URL not configured');
        }

        try {
            $response = Http::timeout(30)->post($webhookUrl, [
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'data' => $data,
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('Webhook error: ' . $response->body());
            }

            return ['sent' => true];
        } catch (\Exception $e) {
            Log::error('ExampleChannel: Send failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getName(): string { return 'Example'; }
    public function isAvailableFor(User $user): bool { return config('notifications.channels.example.enabled', false); }
}
```

**Key points:**
- User-specific settings (webhooks, phone numbers) come from `$user->getSetting('notifications', 'key')`
- Global config (API keys, enabled status) comes from `config('notifications.channels.{channel}.*')`
- Add channel name/description to `NotificationChannelMetadata` trait
- Add user settings to `UserNotificationSettingsController::getRequiredSettings()`

**Key files:** `backend/app/Services/`, `backend/app/Services/Notifications/Channels/`

**Related:** [Recipe: Add Notification Channel](../recipes/add-notification-channel.md), [Anti-patterns: Backend](../anti-patterns/backend.md#dont-create-providerschannels-without-implementing-interface)
