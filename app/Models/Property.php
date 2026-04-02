<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory;
    use SoftDeletes;
    use BelongsToGroup;

    protected $fillable = [
        'group_id',
        'owner_id',
        'type',
        'title',
        'slug',
        'description',
        'bedrooms',
        'bathrooms',
        'parking',
        'area_m2',
        'neighborhood',
        'city',
        'state',
        'lat',
        'lng',
        'price',
        'price_visibility',
        'status',
        'cover_image_url',
        'published_at',
    ];

    protected $casts = [
        'area_m2' => 'decimal:2',
        'lat' => 'float',
        'lng' => 'float',
        'price' => 'decimal:2',
        'published_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $property) {
            if ($property->owner_id) {
                return;
            }

            $authenticatedUserId = auth()->id();

            if ($authenticatedUserId) {
                $property->owner_id = $authenticatedUserId;
                return;
            }

            if ($property->group_id) {
                $property->owner_id = Group::query()->whereKey($property->group_id)->value('owner_id');
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
