<?php

namespace App\Logging;

use App\Events\AppLogCreated;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class BroadcastLogHandler extends AbstractProcessingHandler
{
    private const SENSITIVE_KEYS = [
        'password', 'token', 'secret', 'api_key', 'apikey',
        'authorization', 'cookie', 'credit_card', 'cvv', 'ssn',
    ];

    protected function write(LogRecord $record): void
    {
        if (! config('logging.broadcast_enabled', false)) {
            return;
        }

        try {
            $context = array_merge($record->context, $record->extra);
            $sanitized = $this->sanitize($context);

            event(new AppLogCreated(
                level: $record->level->toPsrLogLevel(),
                message: $record->message,
                context: $sanitized,
                correlationId: $record->extra['correlation_id'] ?? null,
                userId: isset($record->extra['user_id']) ? (int) $record->extra['user_id'] : null,
                timestamp: $record->datetime->format(\DateTimeInterface::ATOM),
            ));
        } catch (\Throwable) {
            // Do not let broadcast failures break logging
        }
    }

    /**
     * Recursively sanitize array, masking values for sensitive keys.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function sanitize(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $keyLower = is_string($key) ? strtolower($key) : '';

            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $needle) {
                if (str_contains($keyLower, $needle)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $out[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $out[$key] = $this->sanitize($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
