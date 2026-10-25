# CSV strategy and drivers

Verified against `iz-ahmad/laravel-turbo-seeder` v1.0.0. The installed README wins on any difference.

## Support matrix

| Driver | Requirements | Notes |
|---|---|---|
| MySQL 5.7+ | Client option `ATTR_LOCAL_INFILE` and server `local_infile=ON` | `LOAD DATA LOCAL INFILE`. Server default is off in MySQL 8. |
| PostgreSQL 9.6+ | None | Client-side `COPY ... FROM STDIN`; no superuser or server file access; only INSERT privilege. |
| SQLite 3.24+ | None | CSV is usually slower than the default strategy; prefer default. |

The CSV strategy has no wrapping transaction by default, does not support `upsert()`, and ignores `commitEvery()`.

## `turbo-seeder:test-connection`

```bash
php artisan turbo-seeder:test-connection          # default connection
php artisan turbo-seeder:test-connection mysql    # named connection
```

Run it before using `useCsvStrategy()` and whenever a connection or CSV problem is suspected. It prints connection name, driver, database, host, port and server version, then which strategies are supported.

- MySQL: reports whether `ATTR_LOCAL_INFILE` is set in `config/database.php` options and whether server `local_infile` is enabled. A warning means the default strategy will be used as a fallback.
- PostgreSQL: confirms client-side COPY is available and shows the CSV temp path.
- SQLite: warns that CSV is not recommended.
- Exit code is failure if the connection cannot be made.

Do not edit `config/database.php` or server settings to enable `local_infile` without user approval. Local infile is a security-sensitive setting; the README advises enabling it only in trusted environments.

## MySQL client option

In `config/database.php` under the connection's `options`: `PDO::MYSQL_ATTR_LOCAL_INFILE => true`, or `Pdo\Mysql::ATTR_LOCAL_INFILE` on PHP 8.4+. Server: `SET GLOBAL local_infile = 1;`, or `local_infile = 1` under `[mysqld]`, or `--local-infile=1` for the Docker image.

## Fallback

If CSV is unavailable (config error), the package falls back to the default strategy when `csv_strategy.fallback_to_default_strategy_on_config_error` is true (default). MySQL runs a preflight check before generating the file. When a CSV run fails and falls back, the generator re-runs from index 0, so non-deterministic values (random, UUID) differ from the failed attempt. Check the Laravel log for the cause.

## Null handling

`csv_strategy.null_marker` (default `\N`) applies to MySQL and SQLite CSV only; PostgreSQL ignores it. If real data equals the marker, the run throws instead of importing it as NULL.

## Other commands

| Command | Purpose |
|---|---|
| `turbo-seeder:clear-cache [--all]` | Remove temporary CSV files (`--all` includes subdirectories). Temp path: `csv_strategy.temp_path` (default `storage/app/turbo-seeder`). |
| `turbo-seeder:benchmark --records=N --connection=X --table=T [--force]` | Creates and drops its own temp table; refuses an existing table; confirms outside local/testing. Do not run against production without user approval. |
| `turbo-seeder:run Seeder` / `--class=Seeder` | Bare class names resolve under `seeder_classes_namespace`; a fully-qualified name bypasses it. |
