<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Deal extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'property_id',
        'seller_agent_id',
        'buyer_agent_id',
        'buyer_name',
        'buyer_contact',
        'commission_percent',
        'commission_split_json',
        'status',
        'notes',
        'started_at',
        'closed_at',
    ];

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'commission_split_json' => 'array',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'commission_split_json' => '{"seller_agent":50,"buyer_agent":50}',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function sellerAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_agent_id');
    }

    public function buyerAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DealMessage::class);
    }

    public function scopeAccessibleBy($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('seller_agent_id', $user->id)
                ->orWhere('buyer_agent_id', $user->id)
                ->orWhereHas('property.group.members', function ($memberQuery) use ($user) {
                    $memberQuery->where('users.id', $user->id);
                });
        });
    }
}
