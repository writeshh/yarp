# Testing Guide for YARP

This document provides guidance on how to test your repositories when using the YARP package.

## Package Tests

YARP comes with a comprehensive test suite to ensure all functionality works as expected. The test suite includes:

- Unit tests for the BaseRepository class
- Unit tests for direct implementations of RepositoryInterface
- Feature tests for the `make:repo` Artisan command

To run all tests:

```bash
composer test
```

To run only unit tests:

```bash
composer test-unit
```

To run only feature tests:

```bash
composer test-feature
```

To generate a coverage report:

```bash
composer test-coverage
```

## Testing Your Repositories

When implementing repositories in your Laravel application using YARP, here are some recommended testing approaches:

### 1. Setting Up Repository Tests

Create a base test case class for your repository tests:

```php
namespace Tests\Unit\Repositories;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class RepositoryTestCase extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Common setup for repository tests
        $this->seed(); // Optional: Seed your database if needed
    }
}
```

### 2. Testing Basic Repository Operations

Test all standard CRUD operations:

```php
namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\UserRepository;

class UserRepositoryTest extends RepositoryTestCase
{
    protected UserRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository(new User());
    }
    
    /** @test */
    public function it_can_get_all_records()
    {
        // Create test data
        User::factory()->count(3)->create();
        
        $results = $this->repository->all();
        
        $this->assertCount(3, $results);
    }
    
    /** @test */
    public function it_can_create_a_record()
    {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ];
        
        $user = $this->repository->create($data);
        
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }
    
    /** @test */
    public function it_can_find_a_record()
    {
        $user = User::factory()->create();
        
        $result = $this->repository->show($user->id);
        
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }
    
    /** @test */
    public function it_can_update_a_record()
    {
        $user = User::factory()->create();
        
        $updated = $this->repository->update(['name' => 'Updated Name'], $user->id);
        
        $this->assertTrue($updated);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name'
        ]);
    }
    
    /** @test */
    public function it_can_delete_a_record()
    {
        $user = User::factory()->create();
        
        $deleted = $this->repository->delete($user->id);
        
        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
```

### 3. Testing Custom Repository Methods

When you add custom methods to your repository, make sure to test them:

```php
/** @test */
public function it_can_find_active_users()
{
    // Create active and inactive users
    User::factory()->count(3)->create(['active' => true]);
    User::factory()->count(2)->create(['active' => false]);
    
    $activeUsers = $this->repository->findActive();
    
    $this->assertCount(3, $activeUsers);
}
```

### 4. Testing with Mocks

You can test services that use repositories by mocking the repository:

```php
namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Services\UserService;
use App\Repositories\UserRepository;
use Mockery;

class UserServiceTest extends TestCase
{
    /** @test */
    public function it_can_register_a_user()
    {
        // Create a mock of the repository
        $repository = Mockery::mock(UserRepository::class);
        
        // Set up expectations
        $repository->shouldReceive('create')
            ->once()
            ->with([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Mockery::any()
            ])
            ->andReturn(new User(['id' => 1, 'name' => 'Test User', 'email' => 'test@example.com']));
        
        // Inject the mock into the service
        $service = new UserService($repository);
        
        // Execute the method
        $user = $service->register('Test User', 'test@example.com', 'password');
        
        // Assert results
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('Test User', $user->name);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

## Best Practices

1. **Use the in-memory SQLite database** for faster tests
2. **Use database transactions** to speed up tests (use `RefreshDatabase` trait)
3. **Test all repository methods**, both standard and custom
4. **Mock repositories** when testing services to isolate the service logic
5. **Create factories** for your models to easily generate test data
6. **Test edge cases** like empty results, invalid IDs, etc.
7. **Consider using Data Providers** for testing similar functionality with different inputs
