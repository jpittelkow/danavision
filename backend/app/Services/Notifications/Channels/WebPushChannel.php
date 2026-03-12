<?php

namespace App\Services\Notifications\Channels;

use App\Models\User;
use App\Services\NotificationTemplateService;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class WebPushChannel implements ChannelInterface
{
    public function send(User $user, string $type, string $title, string $message, array $data = []): array
    {
        $resolved = $this->resolveContent($user, $type, $title, $message, $data);
        $title = $resolved['title'];
        $message = $resolved['body'];

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            throw new \RuntimeException('No Web Push subscriptions for user');
        }

        // Read VAPID keys at send-time so runtime config updates (e.g. from
        // applyNotificationsConfigForRequest) are always picked up, even when
        // this channel instance is cached by the singleton orchestrator.
        $publicKey = config('notifications.channels.webpush.public_key', '');
        $privateKey = config('notifications.channels.webpush.private_key', '');
        $subject = config('notifications.channels.webpush.subject', '') ?: config('app.url');

        if (!$publicKey || !$privateKey) {
            throw new \RuntimeException('VAPID keys not configured');
        }

        $payload = $this->buildPayload($title, $message, $type, $data, $user);

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $webPush = new WebPush($auth, ['TTL' => 86400], 30);
        $results = [];
        $anySuccess = false;

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->p256dh,
                'authToken' => $sub->auth,
                'contentEncoding' => 'aesgcm',
            ]);

            try {
                $report = $webPush->sendOneNotification($subscription, $payload);
            } catch (\Throwable $e) {
                Log::warning('WebPush sendOneNotification threw', [
                    'subscription_id' => $sub->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $results[] = ['endpoint' => $sub->endpoint, 'device' => $sub->device_name, 'error' => $e->getMessage()];
                continue;
            }

            if ($report->isSuccess()) {
                $sub->update(['last_used_at' => now()]);
                $anySuccess = true;
                $results[] = ['endpoint' => $sub->endpoint, 'device' => $sub->device_name, 'sent' => true];
            } elseif ($report->isSubscriptionExpired()) {
                $sub->delete();
                $results[] = ['endpoint' => $sub->endpoint, 'device' => $sub->device_name, 'expired' => true];
                Log::info('Expired Web Push subscription removed', [
                    'subscription_id' => $sub->id,
                    'user_id' => $user->id,
                    'device' => $sub->device_name,
                ]);
            } else {
                Log::warning('WebPush send failed for subscription', [
                    'subscription_id' => $sub->id,
                    'user_id' => $user->id,
                    'reason' => $report->getReason(),
                ]);
                $results[] = ['endpoint' => $sub->endpoint, 'device' => $sub->device_name, 'error' => $report->getReason()];
            }
        }

        if (!$anySuccess && !empty($results)) {
            $allExpired = collect($results)->every(fn ($r) => $r['expired'] ?? false);
            if ($allExpired) {
                $user->setSetting('notifications', 'webpush_enabled', false);
                throw new \RuntimeException('All Web Push subscriptions expired');
            }

            // Collect error reasons so the caller knows why delivery failed.
            $errors = collect($results)->pluck('error')->filter()->unique()->values()->all();
            throw new \RuntimeException('Web Push delivery failed: ' . implode('; ', $errors));
        }

        return [
            'subscriptions_sent' => count(array_filter($results, fn ($r) => $r['sent'] ?? false)),
            'details' => $results,
        ];
    }

    public function getName(): string
    {
        return 'Web Push';
    }

    public function isAvailableFor(User $user): bool
    {
        return config('notifications.channels.webpush.enabled', false)
            && $user->pushSubscriptions()->exists();
    }

    private function buildPayload(string $title, string $message, string $type, array $data, User $user): string
    {
        $payloadArray = [
            'title' => $title,
            'body' => $message,
            'icon' => $data['icon'] ?? '/icon-192.png',
            'badge' => $data['badge'] ?? '/badge.png',
            'tag' => $data['tag'] ?? $type,
            'data' => $data,
            'timestamp' => time() * 1000,
        ];
        $payload = json_encode($payloadArray);

        // Web Push payloads are limited to ~4KB. Leave headroom for encryption overhead.
        $maxPayloadBytes = 3800;
        if (strlen($payload) > $maxPayloadBytes) {
            Log::warning('WebPush payload too large, stripping data', [
                'original_size' => strlen($payload),
                'user_id' => $user->id,
                'type' => $type,
            ]);
            unset($payloadArray['data']);
            $payload = json_encode($payloadArray);

            if (strlen($payload) > $maxPayloadBytes) {
                // Binary search for the right character length that fits the byte limit.
                $lo = 50;
                $hi = mb_strlen($payloadArray['body']);
                while ($lo < $hi) {
                    $mid = intdiv($lo + $hi + 1, 2);
                    $payloadArray['body'] = mb_substr($message, 0, $mid) . '...';
                    if (strlen(json_encode($payloadArray)) <= $maxPayloadBytes) {
                        $lo = $mid;
                    } else {
                        $hi = $mid - 1;
                    }
                }
                $payloadArray['body'] = mb_substr($message, 0, $lo) . '...';
                $payload = json_encode($payloadArray);
            }
        }

        return $payload;
    }

    private function resolveContent(User $user, string $type, string $title, string $message, array $data): array
    {
        $service = app(NotificationTemplateService::class);
        $template = $service->getByTypeAndChannel($type, 'push');
        if (!$template) {
            return ['title' => $title, 'body' => $message];
        }
        $variables = array_merge([
            'user' => ['name' => $user->name, 'email' => $user->email],
            'app_name' => config('app.name', 'Sourdough'),
        ], $data);
        return $service->renderTemplate($template, $variables);
    }
}
