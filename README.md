# YARP — Yet Another Repository Pattern

[![Latest Version on Packagist](https://img.shields.io/packagist/v/writeshh/yarp.svg?style=flat-square)](https://packagist.org/packages/writeshh/yarp)
[![Total Downloads](https://img.shields.io/packagist/dt/writeshh/yarp.svg?style=flat-square)](https://packagist.org/packages/writeshh/yarp)
[![Tests](https://img.shields.io/github/actions/workflow/status/writeshh/yarp/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/writeshh/yarp/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/writeshh/yarp.svg?style=flat-square)](LICENSE.md)

A repository layer for Laravel that gives you a real query API instead of a thin
`all()`/`find()` wrapper — plus a generator that wires the interface bindings for you.

```php
$users = $repository
    ->where('active', true)
    ->with('posts')
    ->latest()
    ->paginate(20);
```

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Generating repositories](#generating-repositories)
- [Using a repository](#using-a-repository)
- [The API](#the-api)
  - [Retrieval](#retrieval)
  - [Pagination](#pagination)
  - [Writes](#writes)
  - [Soft deletes](#soft-deletes)
  - [Aggregates](#aggregates)
  - [Iteration](#iteration)
  - [Fluent constraints](#fluent-constraints)
  - [Escape hatches](#escape-hatches)
- [How the pending query works](#how-the-pending-query-works)
- [The mass-operation guard](#the-mass-operation-guard)
- [Criteria](#criteria)
- [Two repository flavours](#two-repository-flavours)
- [Configuration](#configuration)
- [Customising stubs](#customising-stubs)
- [Testing with repositories](#testing-with-repositories)
- [Upgrading from v1](#upgrading-from-v1)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)

## Requirements

| | |
|---|---|
| PHP | 8.3, 8.4, 8.5 |
| Laravel | 12.x, 13.x |

## Installation

```bash
composer require writeshh/yarp
```

The service provider is auto-discovered. Publish the config if you want to change
paths, namespaces or the default page size:

```bash
php artisan vendor:publish --tag=yarp-config
```

## Generating repositories

```bash
php artisan make:repo User
```

That writes three things:

```
app/Repositories/Contracts/UserRepositoryInterface.php
app/Repositories/UserRepository.php
app/Providers/RepositoryServiceProvider.php     ← created once, appended to after that
```

and binds the interface to the implementation:

```php
$this->app->bind(\App\Repositories\Contracts\UserRepositoryInterface::class, \App\Repositories\UserRepository::class);
```

Register the provider once, in `bootstrap/providers.php` (Laravel 11+):

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,
];
```

### Command options

| Option | Effect |
|---|---|
| `{name...}` | One or more model names. `php artisan make:repo User Post Comment` |
| `--type=extended` | Extend `BaseRepository`. The default. |
| `--type=standalone` | Implement the contract via a trait, inheriting from nothing. |
| `--interface` | Generate the interface even if `yarp.generate_interfaces` is `false`. |
| `--no-interface` | Skip the interface and bind the concrete class instead. |
| `--force` | Overwrite files that already exist. Without it, existing files are left alone. |

Names are normalised for you: `user`, `blog_post` and `UserRepository` all produce
a `BlogPost`-style model name with no doubled suffix.

## Using a repository

Type-hint the **interface**, not the concrete class — that is the whole point of
the binding, and it is what lets you substitute a fake in tests.

```php
use App\Repositories\Contracts\UserRepositoryInterface;

class UserController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function index()
    {
        return view('users.index', [
            'users' => $this->users->where('active', true)->latest()->paginate(),
        ]);
    }

    public function show(int $id)
    {
        return view('users.show', [
            'user' => $this->users->with('posts')->findOrFail($id),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->users->create($request->validated());

        return to_route('users.show', $user);
    }
}
```

Model-specific queries belong on the repository, built from the same fluent API:

```php
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function topScorers(int $limit = 10): Collection
    {
        return $this->orderBy('score', 'desc')->limit($limit)->get();
    }
}
```

Declare it on the interface too, so callers can rely on it:

```php
interface UserRepositoryInterface extends RepositoryInterface
{
    public function topScorers(int $limit = 10): Collection;
}
```

## The API

### Retrieval

| Method | Returns |
|---|---|
| `all(array $columns = ['*'])` | `Collection` — every record, subject to pending constraints |
| `get(array $columns = ['*'])` | `Collection` — synonym of `all()` |
| `find(int\|string $id, array $columns = ['*'])` | `?Model` |
| `findOrFail(int\|string $id, array $columns = ['*'])` | `Model`, throws `ModelNotFoundException` |
| `findMany(array\|Collection $ids, array $columns = ['*'])` | `Collection` |
| `first(array $columns = ['*'])` | `?Model` |
| `firstOrFail(array $columns = ['*'])` | `Model`, throws `ModelNotFoundException` |
| `findWhere($column, $operator = null, $value = null)` | `Collection` |
| `firstWhere($column, $operator = null, $value = null)` | `?Model` |

`findWhere` and `firstWhere` take the same arguments Eloquent's `where()` does:

```php
$repository->findWhere('email', 'ada@example.com');
$repository->findWhere('score', '>', 50);
$repository->findWhere(['active' => true, 'verified' => true]);
$repository->findWhere(fn ($query) => $query->whereNull('banned_at'));
```

### Pagination

| Method | Returns |
|---|---|
| `paginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null)` | `LengthAwarePaginator` |
| `simplePaginate(?int $perPage = null, ...)` | `Paginator` — no count query |

Leave `$perPage` null to use `config('yarp.pagination.per_page')`, falling back to
the model's own `$perPage`.

### Writes

| Method | Returns |
|---|---|
| `create(array $attributes)` | `Model` |
| `createMany(array $records)` | `Collection` — one model event per record |
| `insert(array $records)` | `bool` — single statement, no events, casts or timestamps |
| `update(int\|string $id, array $attributes)` | `?Model` — the updated model, or null if the key missed |
| `updateWhere(array $attributes)` | `int` — rows affected by the pending query |
| `updateOrCreate(array $attributes, array $values = [])` | `Model` |
| `firstOrCreate(array $attributes, array $values = [])` | `Model` |
| `delete(int\|string $id)` | `bool` — false if the key missed |
| `deleteWhere()` | `int` — rows affected by the pending query |

`update()` returns the model rather than a bare `bool`, so you rarely need a
follow-up `find()`:

```php
$user = $repository->update($id, ['name' => 'Ada Lovelace']);

if ($user === null) {
    abort(404);
}

return $user;
```

### Soft deletes

Available on models using `Illuminate\Database\Eloquent\SoftDeletes`. On any other
model these throw a `RepositoryException` that names the model and the method,
rather than failing with an opaque `BadMethodCallException` deeper in Eloquent.

| Method | Returns |
|---|---|
| `withTrashed()` | `$this` |
| `onlyTrashed()` | `$this` |
| `restore(int\|string $id)` | `bool` |
| `forceDelete(int\|string $id)` | `bool` — works on non-soft-deletable models too |

### Aggregates

| Method | Returns |
|---|---|
| `count(string $columns = '*')` | `int` |
| `exists()` / `doesntExist()` | `bool` |
| `sum(string $column)` | `int\|float` |
| `avg(string $column)` | `int\|float\|null` |
| `min(string $column)` / `max(string $column)` | `mixed` |
| `pluck(string $column, ?string $key = null)` | `Support\Collection` |

### Iteration

| Method | Returns |
|---|---|
| `chunk(int $count, Closure $callback)` | `bool` |
| `chunkById(int $count, Closure $callback)` | `bool` — safe while modifying the rows being iterated |
| `cursor()` | `LazyCollection` |

### Fluent constraints

Each of these returns `$this` and accumulates onto the pending query.

| Method |
|---|
| `where($column, $operator = null, $value = null)` |
| `whereIn(string $column, array\|Collection $values)` |
| `whereNotIn(string $column, array\|Collection $values)` |
| `with(array\|string $relations)` |
| `withCount(array\|string $relations)` |
| `orderBy(string $column, string $direction = 'asc')` |
| `latest(?string $column = null)` / `oldest(?string $column = null)` |
| `limit(int $value)` / `offset(int $value)` |
| `scope(string $scope, array $parameters = [])` — apply a model scope |
| `tap(Closure $callback)` — reach the raw builder for one step |
| `pushCriteria(Criterion ...$criteria)` |
| `reset()` — discard pending constraints without executing |

```php
$repository->scope('active')->scope('scoredAbove', [50])->get();

$repository->tap(fn ($query) => $query->whereJsonContains('tags', 'php'))->get();
```

### Escape hatches

| Method | Returns |
|---|---|
| `getModel()` | `Model` — the wrapped instance |
| `query()` | `Builder` — takes ownership of the pending query and resets the repository |
| `newQuery()` | `Builder` — a fresh builder, ignoring pending constraints |
| `transaction(Closure $callback, int $attempts = 1)` | whatever the callback returns |

```php
// Start with the repository, finish with anything Eloquent can do.
$repository->where('active', true)
    ->query()
    ->whereJsonContains('preferences->theme', 'dark')
    ->get();

$repository->transaction(function ($repository) {
    $user = $repository->create([...]);
    $repository->update($user->id, [...]);

    return $user;
});
```

The transaction runs on the wrapped model's own connection, so multi-database
setups behave correctly.

## How the pending query works

Repositories are usually singletons, injected once and reused for the life of a
request. That makes leaked query state a genuine hazard, so YARP is explicit
about it:

- **Fluent methods accumulate.** `where()`, `with()`, `orderBy()` and friends
  build up a pending query and return `$this`.
- **Terminal methods execute and reset.** `get()`, `find()`, `count()`,
  `paginate()`, `delete()` — anything that returns data — runs the pending query
  and then clears it.

```php
$active = $repository->where('active', true)->get();  // 1 user
$everyone = $repository->all();                        // all users — not filtered
```

Without the reset, that second call would silently inherit `active = true`.

**Writes are not affected by pending constraints.** `create()`, `updateOrCreate()`
and `firstOrCreate()` always run against a fresh query, so a stale constraint
cannot quietly change what gets written.

## The mass-operation guard

`updateWhere()` and `deleteWhere()` refuse to run with no constraints applied:

```php
$repository->deleteWhere();
// RepositoryException: ...was called without any constraints, which would
// affect every row in the table.
```

A bare `Model::query()->delete()` empties the table; that is rarely what someone
typing `deleteWhere()` meant. Add a constraint, or opt out deliberately:

```php
$repository->where('expired_at', '<', now())->deleteWhere();   // guarded
$repository->newQuery()->delete();                              // explicit, unguarded
```

## Criteria

Criteria are reusable query fragments — the answer to "this same `where` clause
is duplicated across six methods".

```php
use Illuminate\Database\Eloquent\Builder;
use Writeshh\Yarp\Contracts\Criterion;
use Writeshh\Yarp\Contracts\RepositoryInterface;

/** @implements Criterion<\App\Models\User> */
class VerifiedOnly implements Criterion
{
    public function apply(Builder $query, RepositoryInterface $repository): Builder
    {
        return $query->whereNotNull('email_verified_at');
    }
}
```

```php
$repository->pushCriteria(new VerifiedOnly, new OrderBy('created_at', 'desc'))->get();
```

Four come with the package: `Where`, `WhereIn`, `OrderBy` and `WithRelations`.
`OrderBy` validates the direction rather than interpolating it, so a sort
direction taken from a query string cannot reach the SQL.

Criteria are ordinary objects, which makes them easy to unit test and to pass
around — a controller can hand a repository a criteria list assembled from
request filters without the repository growing a parameter for each one.

## Two repository flavours

```bash
php artisan make:repo User                      # extended (default)
php artisan make:repo User --type=standalone
```

**Extended** inherits from `BaseRepository`:

```php
class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }
}
```

**Standalone** pulls the same implementation in through a trait, leaving the
class free to extend whatever it likes:

```php
class UserRepository implements UserRepositoryInterface
{
    use InteractsWithRepository;

    public function __construct(
        protected User $model,
    ) {}
}
```

Both are the same code — `BaseRepository` is the trait plus a constructor — and
the test suite runs the full behavioural contract against both.

## Configuration

`config/yarp.php` after publishing:

```php
return [
    'default_type' => 'extended',        // or 'standalone'
    'generate_interfaces' => true,

    'repository' => [
        'path' => app_path('Repositories'),
        'namespace' => 'App\\Repositories',
        'suffix' => 'Repository',
    ],

    'interface' => [
        'path' => app_path('Repositories/Contracts'),
        'namespace' => 'App\\Repositories\\Contracts',
        'suffix' => 'RepositoryInterface',
    ],

    'model' => [
        'namespace' => 'App\\Models',
    ],

    'provider' => [
        'path' => app_path('Providers/RepositoryServiceProvider.php'),
        'namespace' => 'App\\Providers',
        'class' => 'RepositoryServiceProvider',
    ],

    'stub_path' => null,

    'pagination' => [
        'per_page' => 15,
    ],
];
```

Domain-driven layouts are a matter of pointing these elsewhere:

```php
'repository' => ['path' => base_path('src/Billing/Data'), 'namespace' => 'Billing\\Data'],
'model'      => ['namespace' => 'Billing\\Entities'],
```

## Customising stubs

```bash
php artisan vendor:publish --tag=yarp-stubs
```

They land in `stubs/yarp/`, which is checked before the packaged copies. Set
`yarp.stub_path` to use a different directory.

| Stub | Used for |
|---|---|
| `Repository.stub` | `--type=extended` |
| `Standalone.stub` | `--type=standalone` |
| `Interface.stub` | the generated interface |
| `ServiceProvider.stub` | `RepositoryServiceProvider`, created once |

Placeholders: `{{ namespace }}`, `{{ class }}`, `{{ model }}`, `{{ modelFqcn }}`,
`{{ interface }}`, `{{ interfaceFqcn }}`, `{{ bindings }}`.

Leave the `// yarp:bindings` marker in your provider stub — it is where later
bindings get inserted. Without it the generator falls back to brace-matching the
`register()` method, which works but is less precise.

## Testing with repositories

Because callers depend on the interface, a fake is a container binding away:

```php
$this->app->bind(UserRepositoryInterface::class, fn () => new InMemoryUserRepository);
```

Or bind a mock for a single expectation:

```php
$this->mock(UserRepositoryInterface::class)
    ->shouldReceive('findOrFail')
    ->with(1)
    ->andReturn(new User(['name' => 'Ada']));
```

See [TESTING.md](TESTING.md) for testing your own repositories and running the
package's suite.

## Upgrading from v1

v2 changes the PHP and Laravel floors and several method signatures. Every break
and its fix is in [UPGRADE.md](UPGRADE.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). In short: `composer check` must pass.

## Security

See [SECURITY.md](SECURITY.md) for reporting vulnerabilities.

## Credits

- [Ritesh Shrestha](https://github.com/writeshh)
- [All Contributors](../../contributors)

## License

MIT. See [LICENSE.md](LICENSE.md).
