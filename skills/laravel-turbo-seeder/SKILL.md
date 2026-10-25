---
name: laravel-turbo-seeder
description: Seed large datasets in Laravel with the iz-ahmad/laravel-turbo-seeder package (TurboSeeder facade, TurboData helpers, turbo-seeder:* artisan commands, MySQL LOAD DATA / PostgreSQL COPY bulk loading). Use when the user wants to seed or bulk-load many rows, speed up slow factory()->create() seeders, or mentions TurboSeeder, turbo-seeder, or bulk seeding in a Laravel project.
---

# Laravel Turbo Seeder

Targets `iz-ahmad/laravel-turbo-seeder` `^1.0` (reference files verified against v1.0.0). Requires PHP 8.2-8.5, Laravel 10-13, and a MySQL, PostgreSQL or SQLite connection (other drivers throw).

## Ground rules

- Act only on documented behaviour: the installed package README and `references/`. Never guess a method, option, default or behaviour.
- If anything is ambiguous or undocumented (requirements, driver, environment, a docs-vs-code conflict), state it upfront and ask before writing code.
- The installed README is the primary source. If it disagrees with `references/`, follow the README and tell the user. If the installed source or observed behaviour contradicts the README, tell the user instead of picking silently.

## Before writing code

1. `composer show iz-ahmad/laravel-turbo-seeder`. Not installed: ask before `composer require`. Version below 1.0 or newer than v1.0.0: say so.
2. Read `vendor/iz-ahmad/laravel-turbo-seeder/README.md`.
3. Confirm with the user if unknown: target tables/models, row counts, DB driver, environment (local vs production), whether truncating is allowed, whether foreign key checks may stay off (they are off by default).

## Choose path and strategy

| Choice | Option | Use when |
|---|---|---|
| Data path | `fromFactory(Model::factory())` | Factory exists, up to ~500k rows, realism matters |
| Data path | `forTable(...)->generate(fn ($i) => [...])` + `TurboData` | 500k+ rows or max speed |
| Strategy | default (bulk INSERT) | Anything; remote DB; SQLite; needs `upsert()` |
| Strategy | `useCsvStrategy()` | Local MySQL/PostgreSQL, 1M+ rows, no upsert |

The two choices are independent. Details: [builder-api](references/builder-api.md), [turbodata](references/turbodata.md).

## Workflow

1. Run `php artisan turbo-seeder:test-connection [connection]` before choosing CSV or diagnosing a driver problem. It shows driver, server version and which strategies are supported. On MySQL it flags missing `ATTR_LOCAL_INFILE` and server `local_infile`. On SQLite it still reports CSV as supported; the advice to prefer the default strategy there comes from the README. See [csv-and-drivers](references/csv-and-drivers.md).
2. Scaffold: `php artisan make:turbo-seeder NameSeeder --table=users --count=100000` (add `--factory=UserFactory` for the factory stub). Edit the generated file: it fills `*_id` columns with `randomInt(1, 100)` placeholders, which must become `TurboData::fromTable(...)` references.
3. Validate with `->dryRun()` and a small `count()` (for example 1,000) on a new or changed seeder, then run the real count. A dry run does the full generate and insert before rolling back, so do not dry-run millions of rows.
4. Run: `php artisan turbo-seeder:run NameSeeder` (add `--force` only with user approval outside local/testing).
5. Verify with `DB::table('t')->count()` and a sample row. `run()` throws on failure, so a returned result always has `success === true`; `$result->recordsInserted` is still worth reporting.

## Rules that prevent common failures

- Closure helpers (`uniqueEmail`, `uniqueUsername`, `uniqueSlug`, `uniqueUuid`, `cycleFrom`, `fromTable`, `fromQuery`, `fromTableStream`) are created outside `generate()` and invoked inside with `$i`.
- Never call `Model::create()`, `bcrypt()` or `now()` per row. Use `TurboData::hashedPassword()` and `TurboData::nowOnce()`.
- Factory relationships: `->recycle($parents)`, not `->for(...)`, and also not `'user_id' => User::factory()` left in `definition()` (no warning is shown for that case). Seed parent tables first, then children.
- FK checks are disabled by default (`performance.disable_foreign_keys`), so on MySQL and SQLite a wrong `*_id` is inserted silently. Draw foreign keys from real parent rows; offer `->enableForeignKeyChecks()` if the user wants enforcement.
- Model events, observers and accessors/mutators do not run. Put derived values in the factory or generator.
- Every NOT NULL column without a DB default must be seeded, or the run fails before writing.
- `upsert()` cannot be used with `useCsvStrategy()`; keys must match a unique/primary index.
- `truncate()` cannot combine with `dryRun()`; `dryRun()` cannot combine with `withoutTransactions()`; `commitEvery()` cannot combine with `useTransactions()`.
- `commitEvery()` leaves committed chunks in place on failure. Warn the user.
- Avoid `fake()->unique()` in factories at large counts (memory grows); use `generate()` with `TurboData::unique*`.
- Outside local/testing every run is refused unless `turbo-seeder:run` is confirmed/`--force`d or the builder calls `->force()`. Never add `->force()` without explicit user approval.
- Failures come in two shapes: builder-level validation throws `InvalidArgumentException` directly from `run()`; execution-time failures (missing table or column, NOT NULL gap, upsert key without a unique index, production guard, database errors) throw `RuntimeException` with the original as `getPrevious()`. Report the underlying message either way.

## References

- [builder-api.md](references/builder-api.md): entry points, every builder method, defaults, conflicts, result DTO, events, config
- [turbodata.md](references/turbodata.md): helper signatures, calling conventions, value formatting
- [csv-and-drivers.md](references/csv-and-drivers.md): CSV setup per driver, `test-connection`, fallback, benchmark/cache commands
- [recipes.md](references/recipes.md): relationships, migrating old seeders, upsert, time-series, huge seeds
