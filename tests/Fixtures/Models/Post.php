<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 */
class Post extends Model
{
    protected $table = 'posts';

    protected $fillable = ['user_id', 'title'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
