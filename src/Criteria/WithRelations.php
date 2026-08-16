<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Criteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Writeshh\Yarp\Contracts\Criterion;
use Writeshh\Yarp\Contracts\RepositoryInterface;

/**
 * Eager load a fixed set of relations.
 *
 * Useful as a named criterion when several call sites need the same relation
 * graph, e.g. `new WithRelations(['author', 'comments.author'])`.
 *
 * @implements Criterion<Model>
 */
class WithRelations implements Criterion
{
    /** @var array<int|string, mixed> */
    protected array $relations;

    /**
     * @param  array<int|string, mixed>|string  $relations
     */
    public function __construct(array|string $relations)
    {
        $this->relations = is_string($relations) ? [$relations] : $relations;
    }

    /** {@inheritDoc} */
    public function apply(Builder $query, RepositoryInterface $repository): Builder
    {
        return $query->with($this->relations);
    }
}
