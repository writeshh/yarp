# Changelog

All notable changes to `yarp` are documented here.

This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
and the format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.0] - 2026-08-16

A substantial release: modern PHP and Laravel floors, two security fixes in the
generator, a much larger repository API, and real interface bindings.

See [UPGRADE.md](UPGRADE.md) for a step-by-step migration.

### Security

- **Fixed a path traversal in `make:repo`.** The `name` argument was concatenated
  directly into a file path, so `php artisan make:repo ../../routes/web` wrote
  outside `app/Repositories`. Names are now validated as bare PHP class names and
  reserved keywords are rejected, before any path is constructed.
- **Fixed world-writable directory creation.** Generated directories were created
  with mode `0777`; they now use `0755`. Check any directory a v1 run created —
  `UPGRADE.md` has the command.
- Resolved 25 advisories across 11 transitive dependencies by moving off the
  Laravel 10 / PHPUnit 10 dependency tree, including high-severity issues in
  `league/commonmark`, `symfony/http-foundation`, `symfony/mime` and
  `phpunit/phpunit`. `composer audit` is clean and runs weekly in CI.
- `OrderBy` validates its sort direction instead of interpolating it, so a
  direction taken from user input cannot reach the SQL.

### Changed — breaking

- Requires PHP 8.3+ (was 8.1+) and Laravel 12 or 13 (was 10+).
- `update()` is now `update(int|string $id, array $attributes): ?Model` — the
  argument order is reversed and it returns the updated model instead of `bool`.
- `with()` is fluent, returning `$this` rather than executing immediately.
  Append `->get()` to restore the old behaviour.
- `query()` now returns the pending builder and resets the repository. Use
  `newQuery()` for v1's always-fresh builder.
- `paginate()` gained `$columns`, `$pageName` and `$page`, and defaults
  `$perPage` to `config('yarp.pagination.per_page')`.
- `findWhere()` accepts an array, string or closure plus an optional operator.
- The generated provider binds the interface to the implementation. v1 emitted
  `bind(UserRepository::class, UserRepository::class)`, which the container
  already does for free.
- `--type=basic` renamed to `--type=extended`; `--type=standalone` replaces the
  hand-written implementation stub and uses a trait instead. `basic` and `base`
  are still accepted.
- `config/config.php` replaced by `config/yarp.php`, which is actually merged
  and publishable.
- Stubs publish to `stubs/yarp` (was `resources/vendor/writeshh/stubs`) and use
  named placeholders such as `{{ model }}` instead of `{{modelName}}`.
- `Service\RepositoryService` → `Services\RepositoryGenerator`, with instance
  methods instead of statics. `Commands\RepositoryPattern` →
  `Commands\MakeRepositoryCommand`. `Facades\RepositoryPattern` → `Facades\Yarp`.

### Added

- **Fluent constraint API** — `where`, `whereIn`, `whereNotIn`, `with`,
  `withCount`, `orderBy`, `latest`, `oldest`, `limit`, `offset`, `scope`, `tap`
  and `reset`, all chainable and reset after each terminal call.
- **Retrieval** — `get`, `findMany`, `first`, `firstOrFail`, `firstWhere`.
- **Writes** — `createMany`, `insert`, `updateWhere`, `updateOrCreate`,
  `firstOrCreate`, `deleteWhere`.
- **Soft deletes** — `withTrashed`, `onlyTrashed`, `restore`, `forceDelete`,
  with a clear exception when the model is not soft-deletable.
- **Aggregates** — `count`, `exists`, `doesntExist`, `sum`, `avg`, `min`, `max`,
  `pluck`.
- **Iteration** — `chunk`, `chunkById`, `cursor`.
- **Criteria** — a `Criterion` contract plus `Where`, `WhereIn`, `OrderBy` and
  `WithRelations`, for reusable query fragments.
- **Transactions** — `transaction()`, running on the model's own connection.
- **Mass-operation guard** — `updateWhere()` and `deleteWhere()` throw rather
  than affecting every row when no constraint is set.
- **Interface generation** — `make:repo` writes a per-model interface by default
  and binds it, so repositories are substitutable in tests. Toggle with
  `--interface` / `--no-interface` or `yarp.generate_interfaces`.
- `make:repo` accepts multiple model names in one invocation.
- `--force` to overwrite existing files; without it they are skipped and
  reported rather than silently clobbered.
- Configurable paths, namespaces and class suffixes for repositories,
  interfaces, models and the provider.
- `InteractsWithRepository` trait, so a repository need not inherit from a
  package class.
- Generic (`@template`) annotations throughout, for IDE and PHPStan inference.
- CI across PHP 8.3/8.4/8.5 × Laravel 12/13, on Linux and Windows, plus a
  lowest-dependency job, PHPStan level 6, Pint and a weekly `composer audit`.

### Fixed

- Repositories no longer leak query state between calls. A constraint applied for
  one call was previously carried into the next on a reused instance.
- Binding insertion into an existing `RepositoryServiceProvider` used
  "the first `}` after the word register", which corrupted any provider whose
  `register()` contained a closure, `match` or nested block. It now uses a marker
  comment, falling back to balanced brace matching.
- The generator wrote reminders to stdout with `echo`, bypassing the console
  output and leaking into piped output. All output now goes through the command.
- The generator's return values were computed and discarded; the command now
  reports exactly which files were created, updated or skipped.
- `publishes()` and command registration moved to `boot()` behind
  `runningInConsole()`, so web requests do not pay for them.
- Removed `loadViewsFrom()` on the stubs directory — stubs are not views.
- `make:repo UserRepository` no longer generates `UserRepositoryRepository`.
- Missing stubs, unwritable directories and unknown types now raise a
  `RepositoryException` with an actionable message.

### Removed

- `laravel/framework` as a direct dev dependency; testbench provides it.
- StyleCI config, replaced by Laravel Pint.

## [1.1.0] - 2025-07-17

Backfilled from the release diff; this version shipped without a changelog entry.

### Added

- `findOrFail()`, `getModel()`, `query()`, `findWhere()` and `with()` on
  `BaseRepository` and `RepositoryInterface`.
- `--type` option on `make:repo`, selecting the basic or extended stub.
- `BaseRepository.stub` for the extended repository type.
- A PHPUnit test suite and `TESTING.md`.

### Changed

- Expanded README with usage and repository-type documentation.

## [1.0.0] - 2025-07-17

- Initial release
- Laravel 10+ and PHP 8.1+ support
- Repository generation command with basic and extended types
- Automatic Service Provider generation
- Support for Repository Pattern implementation

[2.0.0]: https://github.com/writeshh/yarp/compare/1.1.0...2.0.0
[1.1.0]: https://github.com/writeshh/yarp/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/writeshh/yarp/releases/tag/1.0.0
