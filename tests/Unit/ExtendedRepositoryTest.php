<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Writeshh\Yarp\BaseRepository;
use Writeshh\Yarp\Contracts\RepositoryInterface;
use Writeshh\Yarp\Tests\Fixtures\Models\User;
use Writeshh\Yarp\Tests\Fixtures\Repositories\UserRepository;

/**
 * The behavioural contract, run against a BaseRepository subclass.
 */
class ExtendedRepositoryTest extends RepositoryBehaviourTestCase
{
    /**
     * @return RepositoryInterface<User>
     */
    protected function makeRepository(): RepositoryInterface
    {
        return new UserRepository(new User);
    }

    #[Test]
    public function it_is_a_base_repository(): void
    {
        $this->assertInstanceOf(BaseRepository::class, $this->repository);
    }

    #[Test]
    public function custom_methods_can_build_on_the_fluent_api(): void
    {
        $this->makeUser(['name' => 'Low', 'score' => 1]);
        $this->makeUser(['name' => 'Mid', 'score' => 50]);
        $this->makeUser(['name' => 'High', 'score' => 99]);

        /** @var UserRepository $repository */
        $repository = $this->repository;

        $this->assertSame(['High', 'Mid'], $repository->topScorers(2)->pluck('name')->all());
    }
}
