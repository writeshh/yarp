# YARP - Yet Another Repository Pattern

[![Latest Version on Packagist](https://img.shields.io/packagist/v/writeshh/yarp.svg?style=flat-square)](https://packagist.org/packages/writeshh/yarp)
[![Total Downloads](https://img.shields.io/packagist/dt/writeshh/yarp.svg?style=flat-square)](https://packagist.org/packages/writeshh/yarp)
![GitHub Actions](https://github.com/writeshh/yarp/actions/workflows/main.yml/badge.svg)

A simple yet powerful implementation of the Repository Pattern for Laravel. This package provides a quick way to generate repository classes for your Laravel models and standardize data access across your application.

YARP (Yet Another Repository Pattern) helps separate your data access layer from your business logic, making your code more maintainable, testable, and scalable. It automatically generates repository classes, binds them in a service provider, and follows Laravel best practices.

## Requirements

- PHP 8.1+
- Laravel 10.0+

## Installation

You can install the package via composer:

```bash
composer require writeshh/yarp
```

The package will automatically register its service provider if you're using Laravel's package auto-discovery.

## Usage

### Generating a Repository

You can generate a repository for your model using the Artisan command:

```bash
php artisan make:repo User
```

This will create a new repository class at `app/Repositories/UserRepository.php` that implements the standard repository interface.

### Using the Repository in Your Application

The package also automatically creates a service provider binding for your repository. You'll need to register the generated `RepositoryServiceProvider` in your `config/app.php` providers array:

```php
'providers' => [
    // Other providers...
    App\Providers\RepositoryServiceProvider::class,
],
```

Then, you can inject and use your repository in your controllers or services:

```php
use App\Repositories\UserRepository;

class UserController extends Controller
{
    protected $userRepository;
    
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    
    public function index()
    {
        $users = $this->userRepository->all();
        return view('users.index', compact('users'));
    }
    
    public function show($id)
    {
        $user = $this->userRepository->show($id);
        return view('users.show', compact('user'));
    }
    
    public function store(Request $request)
    {
        $user = $this->userRepository->create($request->validated());
        return redirect()->route('users.show', $user->id);
    }
}
```

### Available Repository Types

You can generate two types of repositories:

1. **Basic Repository** - Directly implements RepositoryInterface (default):

```bash
php artisan make:repo User
```

This approach gives you complete control over implementation details. Good for custom repository logic.

2. **Extended Repository** - Extends the BaseRepository class:

```bash
php artisan make:repo User --type=extended
```

This approach uses inheritance to reduce boilerplate code. The BaseRepository already implements all the standard methods, so you only need to add custom methods for your specific needs.

### Customizing Stubs

You can publish the stubs to customize them:

```bash
php artisan vendor:publish --tag=yarp-stubs
```

### Testing

YARP comes with a comprehensive test suite. To run the tests:

```bash
composer test
```

For detailed guidance on testing your repositories and the package itself, see [TESTING.md](TESTING.md).

#### Quick Example

Here's a simple example of testing a repository:

```php
use Tests\TestCase;
use App\Models\User;
use App\Repositories\UserRepository;

class UserRepositoryTest extends TestCase
{
    protected UserRepository $repository;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository(new User());
    }
    
    /** @test */
    public function it_can_create_a_user()
    {
        $user = $this->repository->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password')
        ]);
        
        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertDatabaseHas('users', ['email' => 'john@example.com']);
    }
}
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, create a new issue with the issue tracker.

## Credits

- [Ritesh Shrestha](https://github.com/writeshh)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Why Use the Repository Pattern?

- **Separation of Concerns**: Isolate data access logic from business logic
- **Consistency**: Standardize data access across your application
- **Testability**: Easily mock repositories in your tests
- **Flexibility**: Switch underlying data sources without changing business logic
- **Maintainability**: Centralize data access code for better organization

## Documentation

Full documentation can be found on the [GitHub wiki](https://github.com/writeshh/yarp/wiki).
