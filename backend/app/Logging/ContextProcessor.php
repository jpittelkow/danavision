<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class ContextProcessor implements ProcessorInterface
{
    /**
     * Add correlation_id, user_id, ip_address, and request_uri to log records for tracing.
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        if (app()->bound('correlation_id')) {
            $extra['correlation_id'] = app('correlation_id');
        }

        if (function_exists('auth') && auth()->check()) {
            $extra['user_id'] = auth()->id();
        }

        if (app()->bound('request')) {
            $request = request();
            $extra['ip_address'] = $request->ip();
            $extra['request_uri'] = $request->getRequestUri();
        }

        $record->extra = $extra;

        return $record;
    }
}
