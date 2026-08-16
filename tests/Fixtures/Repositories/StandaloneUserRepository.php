<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Fixtures\Repositories;

use Writeshh\Yarp\Concerns\InteractsWithRepository;
use Writeshh\Yarp\Contracts\RepositoryInterface;
use Writeshh\Yarp\Tests\Fixtures\Models\User;

/**
 * The "standalone" flavour: fulfils the contract via the trait, inheriting from
 * nothing. Both flavours must behave identically, which SharedRepositoryTest
 * asserts by running the same suite against each.
 *
 * @implements RepositoryInterface<User>
 */
class StandaloneUserRepository implements RepositoryInterface
{
    /** @use InteractsWithRepository<User> */
    use InteractsWithRepository;

    public function __construct(
        protected User $model,
    ) {}
}
