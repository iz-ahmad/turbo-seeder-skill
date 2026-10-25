---
name: laravel-turbo-seeder
description: Seed large datasets in Laravel with the iz-ahmad/laravel-turbo-seeder package (TurboSeeder facade, TurboData helpers, turbo-seeder:* artisan commands, MySQL LOAD DATA / PostgreSQL COPY bulk loading). Use when the user wants to seed or bulk-load many rows, speed up slow factory()->create() seeders, or mentions TurboSeeder, turbo-seeder, or bulk seeding in a Laravel project.
---

# Laravel Turbo Seeder

Targets `iz-ahmad/laravel-turbo-seeder` `^1.0` (reference files verified against v1.0.0).

## Ground rules

- Act only on documented behaviour: the installed package README and `references/`. Never guess a method, option, default or behaviour.
- If anything is ambiguous or undocumented (requirements, driver, environment, a docs-vs-code conflict), state it upfront and ask before writing code.
- If the installed README disagrees with `references/`, the installed README wins. Tell the user.

## Before writing code

1. `composer show iz-ahmad/laravel-turbo-seeder`. Not installed: ask before `composer require`. Version below 1.0 or newer than v1.0.0: say so.
2. Read `vendor/iz-ahmad/laravel-turbo-seeder/README.md`.
3. Confirm with the user if unknown: target tables/models, row counts, DB driver, environment (local vs production), whether truncating is allowed.

## Choose path and strategy

| Choice | Option | Use when |
|---|---|---|
| Data path | `fromFactory(Model::factory())` | Factory exists, up to ~500k rows, realism matters |
| Data path | `forTable(...)->generate(fn ($i) => [...])` + `TurboData` | 500k+ rows or max speed |
| Strategy | default (bulk INSERT) | Anything; remote DB; SQLite; needs `upsert()` |
| Strategy | `useCsvStrategy()` | Local MySQL/PostgreSQL, 1M+ rows, no upsert |

The two choices are independent. Details: [builder-api](references/builder-api.md), [turbodata](references/turbodata.md).

## Workflow

1. Run `php artisan turbo-seeder:test-connection [connection]` before choosing CSV or diagnosing a driver problem. It shows driver, server version and which strategies are supported. On MySQL it flags missing `ATTR_LOCAL_INFILE` and server `local_infile`. See [csv-and-drivers](references/csv-and-drivers.md).
2. Scaffold: `php artisan make:turbo-seeder NameSeeder --table=users --count=100000` (add `--factory=UserFactory` for the factory stub). Then edit the generated file.
3. Validate with `->dryRun()` first on a new or changed seeder.
4. Run: `php artisan turbo-seeder:run NameSeeder` (add `--force` only with user approval outside local/testing).
5. Verify: `$result->success`, `$result->recordsInserted`, and `DB::table('t')->count()`.

## Rules that prevent common failures

- Closure helpers (`uniqueEmail`, `uniqueUsername`, `uniqueSlug`, `cycleFrom`, `fromTable`, `fromQuery`, `fromTableStream`) are created outside `generate()` and invoked inside with `$i`.
- Never call `Model::create()`, `bcrypt()` or `now()` per row. Use `TurboData::hashedPassword()` and `TurboData::nowOnce()`.
- Factory relationships: `->recycle($parents)`, not `->for(...)`. Seed parent tables first, then children.
- Model events, observers and accessors/mutators do not run. Put derived values in the factory or generator.
- Every NOT NULL column without a DB default must be seeded, or the run fails before writing.
- `upsert()` cannot be used with `useCsvStrategy()`; keys must match a unique/primary index.
- `truncate()` cannot combine with `dryRun()`; `dryRun()` cannot combine with `withoutTransactions()`; `commitEvery()` cannot combine with `useTransactions()`.
- `commitEvery()` leaves committed chunks in place on failure. Warn the user.
- Avoid `fake()->unique()` in factories at large counts (memory grows); use `generate()` with `TurboData::unique*`.
- Outside local/testing every run is refused unless `turbo-seeder:run` is confirmed/`--force`d or the builder calls `->force()`. A refused `run()` throws `RuntimeException`; the `ProductionEnvironmentException` is its `getPrevious()`. Never add `->force()` without explicit user approval.
- `run()` failures are `RuntimeException` with the original exception as `getPrevious()`. Report the previous message.

## References

- [builder-api.md](references/builder-api.md): entry points, every builder method, defaults, conflicts, result DTO, events, config
- [turbodata.md](references/turbodata.md): helper signatures, calling conventions, value formatting
- [csv-and-drivers.md](references/csv-and-drivers.md): CSV setup per driver, `test-connection`, fallback, benchmark/cache commands
- [recipes.md](references/recipes.md): relationships, migrating old seeders, upsert, time-series, huge seeds
