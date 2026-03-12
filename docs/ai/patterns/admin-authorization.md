# Admin Authorization Pattern

Prevents modifying or deleting the last admin. Use `AdminAuthorizationTrait` for any action that could remove admin access.

## AdminAuthorizationTrait

```php
use App\Http\Traits\AdminAuthorizationTrait;

class UserController extends Controller
{
    use AdminAuthorizationTrait;

    public function destroy(User $user): JsonResponse
    {
        if ($error = $this->ensureNotLastAdmin($user, 'delete')) {
            return $error;
        }
        return $this->deleteResponse('User deleted successfully');
    }

    public function toggleAdmin(User $user): JsonResponse
    {
        if ($error = $this->ensureNotLastAdmin($user, 'remove admin status from')) {
            return $error;
        }
        // ...
    }
}
```

In `GroupController::removeMember`, when the group is the admin group, call `ensureNotLastAdmin($user, 'remove from admin group')` before removing. In `UserController::updateGroups`, before syncing group IDs, if the user is in the admin group and the new list omits the admin group, call `ensureNotLastAdmin($user, 'remove from admin group')`.

Common action verbs: `'delete'`, `'disable'`, `'remove admin status from'`, `'remove from admin group'`.

## Multi-Channel Error Handling

When sending notifications through multiple channels, catch errors per channel and aggregate results:

```php
public function send(User $user, string $type, string $title, string $message, array $data = [], ?array $channels = null): array
{
    $channels = $channels ?? $this->getDefaultChannels();
    $results = [];

    foreach ($channels as $channel) {
        try {
            $channelInstance = $this->getChannelInstance($channel);
            if (!$channelInstance || !$this->isChannelEnabled($channel)) continue;
            if (!$channelInstance->isAvailableFor($user)) continue;

            $result = $channelInstance->send($user, $type, $title, $message, $data);
            $results[$channel] = ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            Log::error("Notification channel {$channel} failed", [
                'user' => $user->id, 'type' => $type, 'error' => $e->getMessage(),
            ]);
            $results[$channel] = ['success' => false, 'error' => $e->getMessage()];
        }
    }

    return $results;
}
```

**Key files:** `backend/app/Http/Traits/AdminAuthorizationTrait.php`, `backend/app/Services/Notifications/NotificationOrchestrator.php`

**Related:** [Recipe: Add admin-protected action](../recipes/add-admin-protected-action.md), [Anti-patterns: Backend](../anti-patterns/backend.md#dont-duplicate-last-admin-checks)

## Implementation Journal

- [User Management Admin (2026-01-29)](../../journal/2026-01-29-user-management-admin.md)
- [Remove is_admin Group Only (2026-01-30)](../../journal/2026-01-30-remove-is-admin-group-only.md)
