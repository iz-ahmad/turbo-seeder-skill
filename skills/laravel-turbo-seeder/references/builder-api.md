# Builder API

Verified against `iz-ahmad/laravel-turbo-seeder` v1.0.0. The installed README wins on any difference.

## Entry points

| Call | Notes |
|---|---|
| `TurboSeeder::forTable(string\|Model)` | Table name, Model class-string or Model instance. Facade/service entry point. |
| `TurboSeeder::fromFactory(Factory)` | Table and columns inferred from the factory's model. |
| builder `->table(...)` | Same as `forTable()`, builder method only. |

Facade: `IzAhmad\TurboSeeder\Facades\TurboSeeder`. Table names must match letters/digits/underscore with an optional single `schema.` prefix. Column names: letters, digits, underscore. A Model argument also sets the connection unless `connection()` is called (explicit wins). For a factory whose model is on a non-default connection, pass `connection()` explicitly; automatic handling on the factory path is not documented.

## Data

| Method | Notes |
|---|---|
| `columns(array)` | If omitted, inferred from keys of the generator's first record (the generator is invoked once with index 0 as a probe). |
| `columnsFromSchema()` | Opt-in. Needs the table set first. Every column except auto-incrementing keys. Generator must still fill NOT NULL columns. |
| `generate(Closure)` | Receives `$index`, returns an associative array. |
| `withTimestamps()` / `withoutTimestamps()` | Fills `created_at`/`updated_at` once per run if absent from the row. Default on for the factory path when the model uses timestamps; off for `generate()`. |
| `withoutColumnValidation()` | Skips the table-exists, column-exists and NOT NULL coverage checks, and also the upsert unique-index check. Only when a trigger/DB default fills the gap. |

## Behaviour

| Method | Notes |
|---|---|
| `count(int)` | Default 1000, minimum 1. |
| `chunkSize(int)` | Minimum 1. Clamped so rows x columns stays within the driver bind limit (65,535 MySQL/PostgreSQL; SQLite auto-detected), so a wide table silently gets a smaller chunk than requested. Config default 1000 (mysql), 800 (pgsql), 500 (sqlite). |
| `truncate()` | MySQL `TRUNCATE` with FK checks off (resets AUTO_INCREMENT); PostgreSQL/SQLite `DELETE` (identity sequences not reset). Committed immediately. |
| `upsert(array $keys)` | Keys must be in the seeded columns and match a unique/primary index. Default strategy only. |
| `dryRun()` | Generates and validates, rolls back. Always runs inside a transaction. |
| `commitEvery(int $chunks)` | Default strategy only. No-op (warning logged) on CSV. No rollback of committed chunks on failure. |
| `retryAttempts(int)` | 1-10, default 3. Retries transient deadlock/lock-timeout only when no transaction is open (for example `withoutTransactions()`). Inside the default wrapping transaction or `commitEvery()` a failed chunk is never retried. |
| `force()` | Bypasses the production guard for this run. |
| `when()` / `unless()` | Conditional chaining. |

## Strategy and tuning

| Method | Notes |
|---|---|
| `useCsvStrategy()` / `useDefaultStrategy()` / `strategy(SeederStrategy)` | Default is bulk INSERT. |
| `disableForeignKeyChecks()` / `enableForeignKeyChecks()` | Config default: disabled during seeding. MySQL runs `SET FOREIGN_KEY_CHECKS=0` and SQLite `PRAGMA foreign_keys=OFF`, so orphan rows are inserted silently; PostgreSQL uses `SET CONSTRAINTS ALL DEFERRED`, which only affects deferrable constraints. Call `enableForeignKeyChecks()` to enforce them. |
| `disableUniqueChecks()` / `enableUniqueChecks()` | MySQL only, opt-in, default off. Can admit duplicates into unique secondary indexes. |
| `disableQueryLog()` / `enableQueryLog()` | Config default: disabled. |
| `useTransactions()` / `withoutTransactions()` | Precedence: dry run (always on), explicit option, `commitEvery()` (off), CSV (off), config (`performance.use_transactions`, default true). |
| `withProgressTracking()` / `withoutProgressTracking()` | Falls back to config `progress.enabled`. |
| `connection(string)` | Overrides model/default connection. |

## Failures: two shapes

**`InvalidArgumentException` thrown directly from `run()`** (builder validation and driver detection, before anything is written):

- `upsert()` + `useCsvStrategy()`
- upsert key not in `columns()`
- `truncate()` + `dryRun()`
- `dryRun()` + `withoutTransactions()`
- `commitEvery()` + `useTransactions()` (explicit true)
- a generator value that is a raw `Closure` (helper not invoked)
- missing table or generator, `count < 1`, invalid table/column name
- unsupported driver (only `mysql`, `pgsql`/`postgresql` and `sqlite` are supported; `mariadb` and `sqlsrv` throw "Unsupported database driver")

**`\RuntimeException` whose `getPrevious()` is the original exception** (everything that fails during execution, which is caught and rethrown by `run()`):

- table missing in the database, unknown column, uncovered NOT NULL column (original is `InvalidArgumentException`)
- upsert key not backed by a unique or primary index (original is `InvalidArgumentException`)
- a refused production run (original is `ProductionEnvironmentException`)
- database, CSV and generator errors

Catch both: `catch (\InvalidArgumentException | \RuntimeException $e)`, and read `$e->getPrevious() ?? $e` for the cause.

## Result

`run()` returns `SeederResultDTO` on success: `success`, `recordsInserted`, `durationSeconds`, `peakMemoryBytes`, `isDryRun`, `errorMessage`, `exception`, `getRecordsPerSecond()`, `getPeakMemoryInMB()`, `toArray()`. Because failures throw, a returned result always has `success === true`. `durationSeconds` excludes truncate time.

## Events

`TurboSeederStarting` (table, count, strategy, connection), `TurboSeederCompleted` (table, result; also for dry runs), `TurboSeederFailed` (table, connection, exception).

## Production guard

Outside `local`/`testing`: `turbo-seeder:run` asks for confirmation (`--force` skips it); `db:seed` or a direct `run()` is refused unless the builder calls `->force()`. A dry run is also subject to the guard. `turbo-seeder:benchmark` asks for confirmation but its internal seed does not set the guard binding or `force()`, so outside local/testing it is refused after the prompt, even with `--force` (verified on v1.0.0).

## Config (`config/turbo-seeder.php`, publish tag `turbo-seeder-config`)

`default_chunk_size`, `chunk_sizes`, `seeder_classes_namespace` (default `Database\Seeders\`), `memory.*`, `performance.*` (query log, FK checks, unique checks, transactions), `csv_strategy.*`, `progress.enabled`, `get_error_trace_on_console`, `max_error_message_length_in_console`. Builder methods override config per run.

## Generated signatures

Regenerated from the package source by the skill repo's maintainer script (not shipped with the installed skill); do not edit by hand. Accessors and raw-option methods (`option()`, `options()`, `toConfiguration()`, `getTable()` and similar) are left out because the package README does not document them.

### Builder methods

<!-- BEGIN GENERATED:builder-methods -->
```
table(Illuminate\Database\Eloquent\Model|string $table): self
columns(array $columns): self
columnsFromSchema(): self
generate(Closure $generator): self
fromFactory(Illuminate\Database\Eloquent\Factories\Factory $factory): self
withTimestamps(bool $enabled = true): self
withoutTimestamps(): self
truncate(bool $enabled = true): self
force(bool $enabled = true): self
count(int $count): self
connection(?string $connection): self
useCsvStrategy(): self
useDefaultStrategy(): self
strategy(IzAhmad\TurboSeeder\Enums\SeederStrategy $strategy): self
chunkSize(int $size): self
withProgressTracking(bool $enabled = true): self
withoutProgressTracking(): self
disableForeignKeyChecks(bool $disabled = true): self
enableForeignKeyChecks(): self
disableUniqueChecks(bool $disabled = true): self
enableUniqueChecks(): self
disableQueryLog(bool $disabled = true): self
enableQueryLog(): self
useTransactions(bool $use = true): self
withoutTransactions(): self
commitEvery(int $chunks): self
when(callable|bool $condition, callable $callback, ?callable $default = null): self
dryRun(bool $enabled = true): self
upsert(array $uniqueBy): self
retryAttempts(int $attempts): self
withoutColumnValidation(): self
unless(callable|bool $condition, callable $callback, ?callable $default = null): self
run(): IzAhmad\TurboSeeder\DTOs\SeederResultDTO
```
<!-- END GENERATED:builder-methods -->

### Config keys and defaults

<!-- BEGIN GENERATED:config-keys -->
```
default_chunk_size = 1000
chunk_sizes.mysql = 1000
chunk_sizes.pgsql = 800
chunk_sizes.sqlite = 500
seeder_classes_namespace = 'Database\\Seeders\\'
memory.limit_mb = 256
memory.gc_threshold_percent = 80
memory.force_gc_after_chunks = 10
performance.disable_query_log = true
performance.disable_foreign_keys = true
performance.disable_unique_checks = false
performance.use_transactions = true
csv_strategy.temp_path = storage_path('app/turbo-seeder')
csv_strategy.buffer_size = 8192
csv_strategy.field_delimiter = ','
csv_strategy.field_enclosure = '"'
csv_strategy.batch_size = 10000
csv_strategy.gc_frequency = 5
csv_strategy.reader_chunk_size_for_sqlite = 500
csv_strategy.fallback_to_default_strategy_on_config_error = true
csv_strategy.null_marker = '\\N'
progress.enabled = true
get_error_trace_on_console = false
max_error_message_length_in_console = 600
```
<!-- END GENERATED:config-keys -->

### Artisan commands

`make:turbo-seeder` options (declared outside the signature, so not shown below): `--table=`, `--count=` (default 1000), `--factory=` (optional factory class), `--force` (overwrite).

<!-- BEGIN GENERATED:commands -->
```
make:turbo-seeder
turbo-seeder:benchmark {--connection= : Database connection name} {--table=benchmark_test : Table name for benchmarking} {--records=100000 : Number of records to seed} {--force : Skip the production confirmation prompt}
turbo-seeder:clear-cache {--all : Clear all temporary files including subdirectories}
turbo-seeder:run {seeder? : The seeder class name (optional)} {--class= : The seeder class name} {--force : Skip the production confirmation prompt}
turbo-seeder:test-connection {connection? : Database connection name to test}
```
<!-- END GENERATED:commands -->
