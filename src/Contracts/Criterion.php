<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * A reusable, named query constraint.
 *
 * Criteria let you extract query fragments that would otherwise be duplicated
 * across repository methods (or leak into controllers) into small testable
 * classes, then compose them:
 *
 *     $repository->pushCriteria(new Active(), new OrderBy('created_at', 'desc'))->get();
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
interface Criterion
{
    /**
     * Apply the constraint to the given query.
     *
     * @param  Builder<TModel>  $query
     * @param  RepositoryInterface<TModel>  $repository
     * @return Builder<TModel>
     */
    public function apply(Builder $query, RepositoryInterface $repository): Builder;
}
