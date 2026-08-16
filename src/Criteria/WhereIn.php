<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Criteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Writeshh\Yarp\Contracts\Criterion;
use Writeshh\Yarp\Contracts\RepositoryInterface;

/**
 * Restrict results to rows whose column matches one of the given values.
 *
 * @implements Criterion<Model>
 */
class WhereIn implements Criterion
{
    /**
     * @param  array<int, mixed>|Collection<int, mixed>  $values
     */
    public function __construct(
        protected string $column,
        protected array|Collection $values,
        protected bool $negate = false,
    ) {}

    /** {@inheritDoc} */
    public function apply(Builder $query, RepositoryInterface $repository): Builder
    {
        return $this->negate
            ? $query->whereNotIn($this->column, $this->values)
            : $query->whereIn($this->column, $this->values);
    }
}
