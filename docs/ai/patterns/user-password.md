# User Password Pattern

The `User` model uses Laravel's `hashed` cast for `password`. Pass **plaintext** when creating or updating users; the cast hashes automatically. Do not use `Hash::make()` in controllers for User password fields, or you will double-hash.

## Usage

```php
// Good - plaintext, cast hashes
User::create(['name' => $n, 'email' => $e, 'password' => $validated['password']]);
$user->update(['password' => $validated['password']]);

// Bad - double-hash when User has hashed cast
User::create(['password' => Hash::make($validated['password'])]);
```

**Key files:** `backend/app/Models/User.php`

**Related:** [Anti-patterns: Backend](../anti-patterns/backend.md#dont-double-hash-user-passwords)
