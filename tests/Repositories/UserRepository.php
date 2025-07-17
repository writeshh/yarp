<?php

namespace Writeshh\Yarp\Tests\Repositories;

use Writeshh\Yarp\BaseRepository;
use Writeshh\Yarp\Tests\Models\User;

class UserRepository extends BaseRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }
    
    // Add custom repository methods for testing
    public function findByEmail(string $email)
    {
        return $this->model->where('email', $email)->first();
    }
}
