# Database Anti-Patterns

### Don't: Use Raw SQL for Simple Queries

```php
// BAD - raw SQL (harder to maintain, potential SQL injection)
$examples = DB::select('SELECT * FROM examples WHERE user_id = ?', [$userId]);

// GOOD - Eloquent ORM
$examples = Example::where('user_id', $userId)->get();
```

### Don't: N+1 Query Problems

```php
// BAD - N+1 queries (1 query + N queries for relations)
$examples = Example::where('user_id', $userId)->get();
foreach ($examples as $example) {
    echo $example->user->name; // Query per iteration!
}

// GOOD - eager loading
$examples = Example::where('user_id', $userId)
    ->with('user')
    ->get();
```

When checking relations that may already be loaded (e.g. in traits), use the loaded relation instead of querying again:

```php
// BAD - inGroup() always queries, even if groups already eager-loaded
public function inGroup(string $slug): bool
{
    return $this->groups()->where('slug', $slug)->exists();
}

// GOOD - check relationLoaded first, then use collection or query
public function inGroup(string $slug): bool
{
    if (array_key_exists('groups', $this->relations) && $this->relationLoaded('groups')) {
        return $this->groups->contains('slug', $slug);
    }
    return $this->groups()->where('slug', $slug)->exists();
}
```

### Don't: Assume SQLite Features Work Everywhere

```php
// BAD - SQLite-specific syntax
DB::statement('PRAGMA foreign_keys = ON');

// GOOD - database-agnostic approach
// Use Laravel's schema builder and query builder
// Test with MySQL/PostgreSQL if targeting production
```
