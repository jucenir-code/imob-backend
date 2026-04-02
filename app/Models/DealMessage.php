<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'deal_id',
        'user_id',
        'message',
        'attachment_type',
        'attachment_url',
        'attachment_mime_type',
        'attachment_original_name',
        'attachment_duration_ms',
    ];

    protected $casts = [
        'attachment_duration_ms' => 'integer',
    ];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DealMessageAttachment::class)->orderBy('position');
    }
}
