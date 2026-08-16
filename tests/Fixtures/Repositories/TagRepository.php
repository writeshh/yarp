<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Fixtures\Repositories;

use Writeshh\Yarp\BaseRepository;
use Writeshh\Yarp\Tests\Fixtures\Models\Tag;

/**
 * @extends BaseRepository<Tag>
 */
class TagRepository extends BaseRepository
{
    public function __construct(Tag $model)
    {
        parent::__construct($model);
    }
}
