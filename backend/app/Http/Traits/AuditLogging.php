<?php

namespace App\Http\Traits;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

trait AuditLogging
{
    /**
     * Log an action to the audit log via AuditService.
     * Resolves AuditService from the container.
     */
    protected function audit(
        string $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?int $userId = null,
        string $severity = 'info'
    ): ?\App\Models\AuditLog {
        $service = app(AuditService::class);
        return $service->log($action, $auditable, $oldValues, $newValues, $userId, null, $severity);
    }
}
