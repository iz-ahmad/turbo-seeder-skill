# TurboData helpers

Verified against `iz-ahmad/laravel-turbo-seeder` v1.0.0. Import: `IzAhmad\TurboSeeder\Helpers\TurboData`.

## Three calling conventions

| Convention | Helpers | Rule |
|---|---|---|
| Returns a closure, call it with `$index` | `cycleFrom`, `uniqueEmail`, `uniqueUsername`, `uniqueSlug`, `uniqueUuid`, `fromTable`, `fromQuery`, `fromTableStream` | Create **outside** `generate()`, invoke inside: `$email($i)` |
| Returns a value per call | `weightedFrom`, `randomFrom`, `randomInt`, `randomFloat`, `randomBool`, `nullable`, `dateRange`, `sequentialDate` | Call directly inside `generate()` |
| Computed once, cached | `nowOnce`, `hashedPassword` | Call inside `generate()`; value reused every row |

Runtime guards: creating a stateful closure helper inside `generate()` logs a warning (state resets every row); `uniqueUuid` is stateless and not checked. Returning an un-invoked closure as a column value throws `InvalidArgumentException`.

## Helpers

| Helper | Notes |
|---|---|
| `cycleFrom(array)` | Round-robin by index. |
| `weightedFrom(['a' => 70, 'b' => 30])` | Relative weights, need not sum to 100; negative weights or zero total throw. |
| `randomFrom(array)` | Uniform pick; empty array throws. |
| `randomInt(min, max)` | Inclusive. |
| `randomFloat(decimals, min, max)` | Note the argument order. |
| `randomBool(probability = 0.5)` | Probability of true. |
| `nullable(probability, fn)` | Probability of null; callback runs only when non-null. |
| `dateRange(from, to)` | Random Carbon date in range. |
| `sequentialDate(start, step, $index)` | Step is a bare unit: second, minute, hour, day, week, month. Logs a warning past the MySQL TIMESTAMP ceiling (2038-01-19); use DATETIME for later dates. |
| `nowOnce()` | Current timestamp string, cached. |
| `hashedPassword($plain = 'password')` | Hashed once, reused. Never hash per row. |
| `uniqueEmail($domain = 'turbo.test')` | `u_<token>_<i>@domain` |
| `uniqueUsername($prefix = 'usr')` | `usr_<token>_<i>` |
| `uniqueSlug($base)` | `my-post-<token>-<i>` |
| `uniqueUuid($prefix = '')` | Fresh UUID per call. |

## Reference pools (foreign keys)

- `fromTable($tableOrModel, $column = 'id', $mode = 'cycle', $connection = null)`: loads the column once on first call, then O(1). Mode `cycle` or `random` (string or `FromTableMode` enum). Seed the referenced table first.
- `fromQuery(fn () => [...])`: loader callback for filters/joins; returns a flat array.
- `fromTableStream($tableOrModel, $column = 'id', pageSize: 10_000, $connection = null)`: keyset-paged, bounded memory; use for reference tables of 500k+ rows.
- `fromTable` and `fromQuery` log a warning past ~500k values.

## Value formatting (automatic)

`null` is NULL; `bool` is 1/0; int/float/string unchanged; DateTime/Carbon is `Y-m-d H:i:s`; BackedEnum is its value; UnitEnum is its name; array, Collection and object are JSON. Custom: `ValueFormatter::extend(Money::class, fn ($m) => $m->getAmount())` in a service provider.

## Generated signatures

Regenerated from the package source by `scripts/generate-api.php`; do not edit by hand.

<!-- BEGIN GENERATED:turbodata-methods -->
```
static cycleFrom(array $values): Closure
static weightedFrom(array $weights): mixed
static randomFrom(array $values): mixed
static randomInt(int $min, int $max): int
static randomFloat(int $decimals, float $min, float $max): float
static randomBool(float $probability = 0.5): bool
static nullable(float $probability, mixed $produce): mixed
static dateRange(string $from, string $to): Carbon\Carbon
static sequentialDate(string $start, string $step, int $index): Carbon\Carbon
static nowOnce(): string
static resetNowOnce(): void
static hashedPassword(string $password = 'password'): string
static resetHashedPasswords(): void
static reset(): void
static markGeneratorActive(bool $active): void
static isInsideGenerator(): bool
static fromTable(Illuminate\Database\Eloquent\Model|string $table, string $column = 'id', IzAhmad\TurboSeeder\Enums\FromTableMode|string $mode = IzAhmad\TurboSeeder\Enums\FromTableMode::CYCLE, ?string $connection = null): Closure
static fromQuery(callable $loader): Closure
static fromTableStream(Illuminate\Database\Eloquent\Model|string $table, string $column = 'id', int $pageSize = 10000, ?string $connection = null): Closure
static uniqueEmail(string $domain = 'turbo.test'): Closure
static uniqueUsername(string $prefix = 'usr'): Closure
static uniqueSlug(string $base): Closure
static uniqueUuid(string $prefix = ''): Closure
```
<!-- END GENERATED:turbodata-methods -->
