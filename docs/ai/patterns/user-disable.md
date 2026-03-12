# User Disable Pattern

When supporting disable/enable for user accounts, use a nullable `disabled_at` timestamp and block disabled users at login:

- **Model**: Add `disabled_at` to `$fillable` and `$casts` (datetime). Add `isDisabled(): bool` helper that returns `$this->disabled_at !== null`.
- **Login**: In `AuthController::login()`, after `Auth::attempt()` succeeds, check `$user->isDisabled()`. If disabled, call `Auth::logout()` and return 403 with a clear message (e.g. "This account has been disabled. Please contact your administrator.").
- **Admin toggle**: In `UserController::toggleDisabled()`, prevent disabling self or the last admin (use `AdminAuthorizationTrait::ensureNotLastAdmin()`). Toggle by setting `disabled_at` to `now()` or `null`.
- **API**: Expose `disabled_at` in user list/detail responses so the frontend can show Active/Disabled badge and Enable/Disable action.

**Key files:** `backend/app/Models/User.php`, `backend/app/Http/Controllers/Api/AuthController.php`, `backend/app/Http/Controllers/Api/UserController.php`, `backend/app/Http/Traits/AdminAuthorizationTrait.php`

**Related:** [Admin Authorization](admin-authorization.md)
