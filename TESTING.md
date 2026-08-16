# Testing

Two parts: testing repositories in *your* application, and running YARP's own
suite.

## Testing your repositories

### Substituting a fake

Because your controllers type-hint the generated interface, swapping the
implementation is a container binding:

```php
use App\Repositories\Contracts\UserRepositoryInterface;

class UserControllerTest extends TestCase
{
    public function test_index_lists_active_users(): void
    {
        $this->app->bind(UserRepositoryInterface::class, InMemoryUserRepository::class);

        $this->get('/users')->assertOk();
    }
}
```

Or mock a single expectation:

```php
$this->mock(UserRepositoryInterface::class)
    ->shouldReceive('findOrFail')
    ->once()
    ->with(1)
    ->andReturn(new User(['name' => 'Ada']));

$this->get('/users/1')->assertOk()->assertSee('Ada');
```

This is the practical payoff of binding interfaces rather than concrete classes —
and the reason `make:repo` generates an interface by default.

### Testing the repository itself

Repositories talk to the database, so test them against a real one:

```php
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new UserRepository(new User);
    }

    #[Test]
    public function it_creates_a_user(): void
    {
        $user = $this->repository->create([
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->assertTrue($user->exists);
        $this->assertDatabaseHas('users', ['email' => 'ada@example.com']);
    }

    #[Test]
    public function it_returns_the_updated_model(): void
    {
        $user = User::factory()->create(['name' => 'Ada']);

        $updated = $this->repository->update($user->id, ['name' => 'Ada Lovelace']);

        $this->assertSame('Ada Lovelace', $updated?->name);
    }

    #[Test]
    public function it_returns_null_when_the_key_misses(): void
    {
        $this->assertNull($this->repository->update(999, ['name' => 'Nobody']));
    }
}
```

You only need to test the methods *you* added — the inherited API is covered by
this package's suite.

### Things worth asserting

**Constraints do not leak.** If you keep a repository instance across calls,
prove the reset holds for the paths you rely on:

```php
$this->assertCount(1, $this->repository->where('active', true)->get());
$this->assertCount(3, $this->repository->all());
```

**The mass-operation guard.** `updateWhere()` and `deleteWhere()` throw when
unconstrained:

```php
$this->expectException(RepositoryException::class);

$this->repository->deleteWhere();
```

**Soft deletes on non-soft-deletable models** throw rather than failing deeper in
Eloquent:

```php
$this->expectException(RepositoryException::class);

$this->repository->withTrashed();
```

## Running the package's suite

```bash
composer install
composer test
```

Individual suites and tools:

```bash
composer test-unit        # tests/Unit
composer test-feature     # tests/Feature
composer test-coverage    # HTML coverage into coverage/
composer analyse          # PHPStan level 6
composer format           # Pint, applying fixes
composer format-check     # Pint, reporting only
composer check            # format-check + analyse + test — what CI runs
```

Filter to a single test:

```bash
vendor/bin/phpunit --filter it_refuses_an_unconstrained_mass_delete
```

### How the suite is arranged

```
tests/
├── TestCase.php                        Testbench base, sqlite :memory:, seed helpers
├── Fixtures/
│   ├── Models/                         User, Post, Tag (Tag is soft-deletable)
│   └── Repositories/                   one per repository flavour
├── Unit/
│   ├── RepositoryBehaviourTestCase.php the full behavioural contract
│   ├── ExtendedRepositoryTest.php      that contract, via BaseRepository
│   ├── StandaloneRepositoryTest.php    that contract, via the trait
│   ├── PendingQueryTest.php            query state does not leak between calls
│   ├── SoftDeleteTest.php              trashed/restore/forceDelete and guards
│   ├── CriteriaTest.php                the criteria system
│   └── ClassNameTest.php               name validation, including traversal input
├── Feature/
│   ├── GeneratorTestCase.php           points the generator at a temp directory
│   ├── MakeRepositoryCommandTest.php   the command end to end
│   ├── GeneratorSecurityTest.php       traversal refusal and file permissions
│   ├── ProviderBindingTest.php         binding insertion into real providers
│   └── ServiceProviderTest.php         config merge, bindings, publishing
└── database/migrations/
```

`RepositoryBehaviourTestCase` is abstract and runs twice — once per flavour — so
`BaseRepository` and `InteractsWithRepository` cannot drift apart.

Feature tests write to a throwaway directory under the system temp dir and clean
up afterwards, and `assertParses()` shells out to `php -l` so a stub change that
generates invalid PHP fails immediately.

### Suite configuration

`phpunit.xml` runs in random order and treats risky tests, warnings and
deprecations as failures. If a Laravel upgrade starts emitting deprecations, the
suite fails rather than accumulating them quietly.

### Testing against other supported versions

```bash
composer require --dev "laravel/framework:12.*" "orchestra/testbench:10.*" --with-all-dependencies
composer test
```

CI covers PHP 8.3/8.4/8.5 × Laravel 12/13 on Linux, one Windows job, and a
lowest-dependency job that proves the declared floor is real.
