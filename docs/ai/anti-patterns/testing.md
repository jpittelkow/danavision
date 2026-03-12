# Testing Anti-Patterns

### Don't: Test Without Authentication

```php
// BAD - test will fail (401)
it('lists examples', function () {
    $this->getJson('/api/examples')
        ->assertOk();
});

// GOOD - authenticate the request
it('lists examples', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/examples')
        ->assertOk();
});
```

### Don't: Skip Testing User Isolation

```php
// BAD - only tests happy path
it('returns examples', function () {
    $user = User::factory()->create();
    Example::factory()->count(3)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->getJson('/api/examples')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

// GOOD - also test user can't see other users' data
it('only returns current user examples', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Example::factory()->count(3)->create(['user_id' => $user->id]);
    Example::factory()->count(5)->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->getJson('/api/examples')
        ->assertOk()
        ->assertJsonCount(3, 'data'); // Not 8!
});
```
