<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default repository type
    |--------------------------------------------------------------------------
    |
    | Which flavour `make:repo` generates when no --type is given.
    |
    |   "extended"   — the class extends Writeshh\Yarp\BaseRepository.
    |   "standalone" — the class implements RepositoryInterface and pulls the
    |                  implementation in via the InteractsWithRepository trait,
    |                  so nothing inherits from a package class.
    |
    */

    'default_type' => 'extended',

    /*
    |--------------------------------------------------------------------------
    | Generate interfaces
    |--------------------------------------------------------------------------
    |
    | When true, `make:repo User` also writes a UserRepositoryInterface and binds
    | that interface to the concrete repository. Type-hint the interface in your
    | controllers and the container resolves the implementation — which is the
    | point of the pattern, and what makes repositories swappable in tests.
    |
    | Set to false to generate the concrete class only.
    |
    */

    'generate_interfaces' => true,

    /*
    |--------------------------------------------------------------------------
    | Generated repositories
    |--------------------------------------------------------------------------
    */

    'repository' => [
        'path' => app_path('Repositories'),
        'namespace' => 'App\\Repositories',
        'suffix' => 'Repository',
    ],

    /*
    |--------------------------------------------------------------------------
    | Generated interfaces
    |--------------------------------------------------------------------------
    */

    'interface' => [
        'path' => app_path('Repositories/Contracts'),
        'namespace' => 'App\\Repositories\\Contracts',
        'suffix' => 'RepositoryInterface',
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Where your Eloquent models live, so the generated classes can import them.
    |
    */

    'model' => [
        'namespace' => 'App\\Models',
    ],

    /*
    |--------------------------------------------------------------------------
    | Binding service provider
    |--------------------------------------------------------------------------
    |
    | The provider `make:repo` creates and appends bindings to. Remember to
    | register it in bootstrap/providers.php (Laravel 11+) or the providers
    | array in config/app.php.
    |
    */

    'provider' => [
        'path' => app_path('Providers/RepositoryServiceProvider.php'),
        'namespace' => 'App\\Providers',
        'class' => 'RepositoryServiceProvider',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom stubs
    |--------------------------------------------------------------------------
    |
    | Publish the stubs with `php artisan vendor:publish --tag=yarp-stubs` and
    | they land in stubs/yarp, which is checked automatically. Set this to point
    | somewhere else instead.
    |
    */

    'stub_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default page size for paginate() and simplePaginate() when no explicit
    | size is passed. Set to null to defer to each model's own $perPage.
    |
    */

    'pagination' => [
        'per_page' => 15,
    ],

];
