# Upgrade guide

## v1 → v2

v2 raises the PHP and Laravel floors, fixes two security issues in the generator,
and reshapes several methods that were awkward or lossy in v1.

Most applications need three changes: bump the requirement, swap the argument
order on `update()`, and re-run `make:repo` (or hand-edit the generated provider
so it binds interfaces).

---

## Requirements

| | v1 | v2 |
|---|---|---|
| PHP | 8.1+ | **8.3+** |
| Laravel | 10.0+ | **12.x, 13.x** |

Laravel 10 and 11 are no longer supported. Stay on `writeshh/yarp:^1.0` if you
cannot upgrade the framework yet.

---

## Security fixes

Two issues in the v1 generator are fixed. Neither is remotely exploitable — both
require someone able to run Artisan — but both are worth knowing about if you
wrapped `make:repo` in any automation that passes through untrusted input.

### Path traversal via the `name` argument

v1 concatenated the command argument straight into a file path:

```php
file_put_contents(base_path("/app/Repositories/{$name}Repository.php"), $template);
```

`php artisan make:repo ../../routes/web` wrote outside `app/Repositories`.

v2 validates the name against `/^[A-Za-z_][A-Za-z0-9_]*$/` and rejects reserved
PHP keywords before any path is built. Separators, dots, null bytes and
namespaced input are refused with a `RepositoryException`.

**Action:** none, unless you relied on passing a path-like name. Nested
repositories are now configured via `yarp.repository.namespace` and
`yarp.repository.path` instead.

### World-writable directories

v1 created `app/Repositories` with mode `0777`. On any host where the deploying
user's umask did not clamp it down, the directory was world-writable — meaning
any local user could drop a PHP file into a directory your application loads.

v2 uses `0755`.

**Action:** check any directory a v1 run created, and tighten it if needed:

```bash
find app/Repositories app/Providers -type d -perm -o+w -exec chmod 755 {} +
```

---

## Breaking API changes

### `update()` — argument order and return type

The old signature threw away the model you almost always wanted next.

```php
// v1
public function update(array $data, string|int $id): bool

$ok = $repository->update(['name' => 'Ada'], $id);
```

```php
// v2
public function update(int|string $id, array $attributes): ?Model

$user = $repository->update($id, ['name' => 'Ada']);

if ($user === null) {
    abort(404);
}
```

The argument order is reversed, so v1 calls fail with a `TypeError` rather than
silently doing the wrong thing. Fix them by swapping the arguments and treating
`null`, not `false`, as "not found".

### `with()` is now fluent

```php
// v1 — executed immediately
$users = $repository->with(['posts']);            // Collection

// v2 — returns $this, chain a terminal call
$users = $repository->with('posts')->get();       // Collection
```

This is what makes `->with(...)->where(...)->paginate()` possible. If you want
the old one-liner, append `->get()`.

### `query()` consumes pending constraints

```php
// v1 — always a fresh builder
$repository->query();

// v2 — carries pending constraints and resets the repository
$repository->where('active', true)->query();      // Builder with the where applied
$repository->newQuery();                          // v1's behaviour
```

**Action:** replace bare `query()` calls with `newQuery()` if you relied on
always getting an unconstrained builder.

### `paginate()` signature

```php
// v1
public function paginate(int $perPage = 15): LengthAwarePaginator

// v2
public function paginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
```

Existing `paginate(25)` calls are unaffected. Bare `paginate()` now uses
`config('yarp.pagination.per_page')` (default 15) instead of a hard-coded 15.

### `findWhere()` signature widened

```php
// v1
public function findWhere(string $column, mixed $value): Collection

// v2
public function findWhere(array|string|Closure $column, mixed $operator = null, mixed $value = null): Collection
```

Existing two-argument calls behave identically. This is source-compatible unless
you implemented `RepositoryInterface` by hand.

### `deleteWhere()` and `updateWhere()` are guarded

These are new in v2, but worth flagging: both throw a `RepositoryException` when
called with no constraints, rather than affecting every row. Use
`newQuery()->delete()` to bypass the guard deliberately.

---

## Moved and renamed classes

| v1 | v2 |
|---|---|
| `Writeshh\Yarp\Service\RepositoryService` | `Writeshh\Yarp\Services\RepositoryGenerator` |
| `Writeshh\Yarp\Commands\RepositoryPattern` | `Writeshh\Yarp\Commands\MakeRepositoryCommand` |
| `Writeshh\Yarp\Facades\RepositoryPattern` | `Writeshh\Yarp\Facades\Yarp` |

The `Yarp` facade alias is unchanged, and the generator's methods are no longer
static:

```php
// v1
RepositoryService::ImplementNow('User', 'basic');

// v2
Yarp::generate('User');
// or: app(RepositoryGenerator::class)->generate('User');
```

`Writeshh\Yarp\BaseRepository` and `Writeshh\Yarp\Contracts\RepositoryInterface`
keep their names and locations.

---

## Config file renamed

`config/config.php` (empty and never loaded in v1) is replaced by a real
`config/yarp.php`.

```bash
php artisan vendor:publish --tag=yarp-config
```

---

## Generated code changes

### The provider now binds interfaces

v1 emitted a binding that did nothing:

```php
// v1 — the container already resolves a concrete class to itself
$this->app->bind(\App\Repositories\UserRepository::class, \App\Repositories\UserRepository::class);
```

```php
// v2
$this->app->bind(\App\Repositories\Contracts\UserRepositoryInterface::class, \App\Repositories\UserRepository::class);
```

**Action:** re-run `php artisan make:repo <Model> --force` for each model, or
edit the provider by hand. Then switch your controllers to type-hint the
interface. To keep v1's behaviour instead, set `yarp.generate_interfaces` to
`false` or pass `--no-interface`.

### `--type=basic` is now `--type=extended`

Both v1 types produced a class extending `BaseRepository` in practice. v2 names
them `extended` (default) and `standalone`. `--type=basic` and `--type=base`
still work and map to `extended`.

The v1 "basic" stub hand-wrote every interface method. With the v2 interface at
40+ methods that stub would be unmaintainable, so `--type=standalone` now pulls
the implementation in through the `InteractsWithRepository` trait instead.

### Stub locations and placeholders

| | v1 | v2 |
|---|---|---|
| Publish path | `resources/vendor/writeshh/stubs` | `stubs/yarp` |
| Placeholder | `{{modelName}}` | `{{ model }}`, `{{ class }}`, `{{ namespace }}`, … |
| Stub for standalone | `Repository.stub` | `Standalone.stub` |
| Stub for extended | `BaseRepository.stub` | `Repository.stub` |

**Action:** if you published and customised v1 stubs, re-publish and re-apply
your changes:

```bash
rm -rf resources/vendor/writeshh/stubs
php artisan vendor:publish --tag=yarp-stubs
```

---

## Checklist

- [ ] PHP 8.3+ and Laravel 12 or 13
- [ ] `composer require writeshh/yarp:^2.0`
- [ ] Swap `update($data, $id)` → `update($id, $data)`, handle `null` not `false`
- [ ] Append `->get()` to any `with(...)` call used as a terminal
- [ ] Replace bare `query()` with `newQuery()` where you wanted a clean builder
- [ ] `php artisan vendor:publish --tag=yarp-config`
- [ ] Re-publish and re-apply stub customisations, if any
- [ ] Re-run `make:repo --force`, or fix the provider bindings by hand
- [ ] Type-hint interfaces rather than concrete repositories
- [ ] Check permissions on directories a v1 run created
