<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Fixtures\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Writeshh\Yarp\BaseRepository;
use Writeshh\Yarp\Tests\Fixtures\Models\User;

/**
 * The "extended" flavour: inherits everything from BaseRepository.
 *
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * A custom method built on the fluent API, as generated repositories would.
     *
     * @return Collection<int, User>
     */
    public function topScorers(int $limit = 3): Collection
    {
        return $this->orderBy('score', 'desc')->limit($limit)->get();
    }
}
