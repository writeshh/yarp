<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Criteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Writeshh\Yarp\Contracts\Criterion;
use Writeshh\Yarp\Contracts\RepositoryInterface;

/**
 * A plain equality (or operator) constraint, expressed as a criterion so it can
 * be stored, passed around and composed with others.
 *
 * @implements Criterion<Model>
 */
class Where implements Criterion
{
    public function __construct(
        protected string $column,
        protected mixed $value,
        protected string $operator = '=',
    ) {}

    /** {@inheritDoc} */
    public function apply(Builder $query, RepositoryInterface $repository): Builder
    {
        return $query->where($this->column, $this->operator, $this->value);
    }
}
