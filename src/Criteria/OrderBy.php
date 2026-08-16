<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Criteria;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Writeshh\Yarp\Contracts\Criterion;
use Writeshh\Yarp\Contracts\RepositoryInterface;

/**
 * Sort results by a column.
 *
 * The direction is validated rather than interpolated blindly, so a user-supplied
 * sort direction cannot be smuggled into the SQL.
 *
 * @implements Criterion<Model>
 */
class OrderBy implements Criterion
{
    public function __construct(
        protected string $column,
        protected string $direction = 'asc',
    ) {}

    /** {@inheritDoc} */
    public function apply(Builder $query, RepositoryInterface $repository): Builder
    {
        $direction = strtolower($this->direction) === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($this->column, $direction);
    }
}
