# Recipes

Verified against `iz-ahmad/laravel-turbo-seeder` v1.0.0. Adapt table, column and model names to the user's project; confirm them first.

## Migrate a slow seeder

```php
// before
User::factory()->count(10_000)->create();

// after (keeps the factory)
TurboSeeder::fromFactory(User::factory())->count(10_000)->run();
```

For 500k+ rows, switch to `generate()` with `TurboData` helpers.

## Generator path

```php
$uniqueEmail = TurboData::uniqueEmail();

TurboSeeder::forTable(User::class)
    ->columns(['name', 'email', 'password', 'role'])
    ->generate(fn ($i) => [
        'name' => "User {$i}",
        'email' => $uniqueEmail($i),
        'password' => TurboData::hashedPassword(),
        'role' => TurboData::weightedFrom(['user' => 85, 'admin' => 15]),
    ])
    ->withTimestamps()
    ->count(1_000_000)
    ->run();
```

## Belongs-to relationships

Factory path: seed parents, then `recycle()` them.

```php
TurboSeeder::fromFactory(User::factory())->count(10_000)->run();
$users = User::select('id')->get();
TurboSeeder::fromFactory(Post::factory()->recycle($users))->count(1_000_000)->run();
```

A factory using `->for(User::factory())` without `recycle()` triggers a warning because each row would run an Eloquent create.

Generator path: `$userIds = TurboData::fromTable(User::class);` then `'user_id' => $userIds($i)`.

## Has-many, belongs-to-many, pivots

The parent's generated key is unavailable inside a single bulk call. Seed each table separately, in dependency order. For pivots, draw ids from both tables:

```php
$postIds = TurboData::fromTable('posts');
$tagIds = TurboData::fromTable('tags');

TurboSeeder::forTable('post_tag')
    ->columns(['post_id', 'tag_id'])
    ->generate(fn ($i) => ['post_id' => $postIds($i), 'tag_id' => $tagIds($i)])
    ->count(200_000)
    ->run();
```

## Upsert (default strategy only)

```php
TurboSeeder::forTable('products')
    ->columns(['sku', 'name', 'price'])
    ->generate(fn ($i) => ['sku' => "SKU-{$i}", 'name' => "Product {$i}", 'price' => TurboData::randomFloat(2, 1, 500)])
    ->upsert(['sku'])
    ->count(10_000)
    ->run();
```

`sku` must have a unique or primary index.

## Dry run

```php
$result = $builder->dryRun()->run();   // $result->isDryRun === true; nothing committed
```

## Time series

```php
'recorded_at' => TurboData::sequentialDate('2024-01-01', 'hour', $i)
```

## Very large seeds

```php
->chunkSize(5_000)->commitEvery(20)->withProgressTracking()
```

A mid-run failure leaves committed chunks in the table. Tell the user, and plan `truncate()` plus a re-run for a clean retry.

## Listen to events

Register listeners for `TurboSeederStarting`, `TurboSeederCompleted`, `TurboSeederFailed` (in `IzAhmad\TurboSeeder\Events`) like any Laravel event.

## Catching a failed run

```php
try {
    $builder->run();
} catch (\RuntimeException $e) {
    $original = $e->getPrevious();   // the real cause, e.g. ProductionEnvironmentException
}
```
