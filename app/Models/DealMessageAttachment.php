<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealMessageAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'deal_message_id',
        'type',
        'url',
        'mime_type',
        'original_name',
        'duration_ms',
        'position',
    ];

    protected $casts = [
        'duration_ms' => 'integer',
        'position' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(DealMessage::class, 'deal_message_id');
    }
}
