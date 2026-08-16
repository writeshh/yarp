<?php

declare(strict_types=1);

namespace Writeshh\Yarp;

use Illuminate\Database\Eloquent\Model;
use Writeshh\Yarp\Concerns\InteractsWithRepository;
use Writeshh\Yarp\Contracts\RepositoryInterface;

/**
 * A batteries-included Eloquent repository.
 *
 * Extend this class and type-hint your model in the constructor:
 *
 *     class UserRepository extends BaseRepository
 *     {
 *         public function __construct(User $model)
 *         {
 *             parent::__construct($model);
 *         }
 *     }
 *
 * Every method lives in {@see InteractsWithRepository}; this class only adds the
 * constructor. If you would rather not inherit from a package class, use that
 * trait directly instead.
 *
 * @template TModel of Model
 *
 * @uses InteractsWithRepository<TModel>
 *
 * @implements RepositoryInterface<TModel>
 */
abstract class BaseRepository implements RepositoryInterface
{
    /** @use InteractsWithRepository<TModel> */
    use InteractsWithRepository;

    /**
     * The model the repository wraps.
     *
     * @var TModel
     */
    protected Model $model;

    /**
     * @param  TModel  $model
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }
}
