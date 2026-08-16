<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A soft-deletable model, for exercising withTrashed/onlyTrashed/restore.
 *
 * @property int $id
 * @property string $label
 */
class Tag extends Model
{
    use SoftDeletes;

    protected $table = 'tags';

    protected $fillable = ['label'];
}
