<?php

declare(strict_types=1);

namespace Writeshh\Yarp\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $active
 * @property int $score
 */
class User extends Model
{
    use HasFactory;

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'active', 'score'];

    protected $casts = [
        'active' => 'boolean',
        'score' => 'integer',
    ];

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeScoredAbove(Builder $query, int $score): void
    {
        $query->where('score', '>', $score);
    }
}
