<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class GroupUser extends Pivot
{
    protected $table = 'group_user';

    protected $fillable = [
        'group_id',
        'user_id',
        'role_in_group',
    ];

    public $timestamps = true;

    public static function booted(): void
    {
        static::saving(function (self $pivot) {
            if ($pivot->exists === false && $pivot->role_in_group === null) {
                $pivot->role_in_group = 'member';
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
