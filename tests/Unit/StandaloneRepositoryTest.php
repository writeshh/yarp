<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Writeshh\Yarp\BaseRepository;
use Writeshh\Yarp\Contracts\RepositoryInterface;
use Writeshh\Yarp\Tests\Fixtures\Models\User;
use Writeshh\Yarp\Tests\Fixtures\Repositories\StandaloneUserRepository;

/**
 * The same behavioural contract, run against a class that uses the trait rather
 * than inheriting from BaseRepository.
 */
class StandaloneRepositoryTest extends RepositoryBehaviourTestCase
{
    /**
     * @return RepositoryInterface<User>
     */
    protected function makeRepository(): RepositoryInterface
    {
        return new StandaloneUserRepository(new User);
    }

    #[Test]
    public function it_fulfils_the_contract_without_inheriting_from_the_package(): void
    {
        $this->assertInstanceOf(RepositoryInterface::class, $this->repository);
        $this->assertNotInstanceOf(BaseRepository::class, $this->repository);
    }
}
