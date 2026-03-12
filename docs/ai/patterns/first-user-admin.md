# First User Gets Admin Pattern

The first registered user automatically becomes an admin. Use this pattern in registration and SSO flows.

## Usage

```php
use App\Models\User;
use App\Services\GroupService;

$groupService = app(GroupService::class);
$isFirstUser = User::count() === 0;

if ($isFirstUser) {
    $groupService->ensureDefaultGroupsExist();
}

$user = User::create([
    'name' => $validated['name'],
    'email' => $validated['email'],
    'password' => $validated['password'],
]);

if ($isFirstUser) {
    $user->assignGroup('admin');
} else {
    $groupService->assignDefaultGroupToUser($user);
}
```

- Check `User::count() === 0` before creating the user
- Ensure default groups exist before assigning
- First user gets `admin` group, subsequent users get default group

**Used in:** `AuthController::register()`, `SSOService::createUserFromSocialite()`

**Related:** [Permission Checking](permission-checking.md), [Admin Authorization](admin-authorization.md)
