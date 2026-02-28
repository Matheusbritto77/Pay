<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User; // Added this import

class CrmMessage extends Model
{
    protected $fillable = [
        'conversation_id', 'from_me', 'type', 'content',
        'media_url', 'message_timestamp', 'status', 'remote_id',
        'is_internal', 'user_id',
    ];

    protected $casts = [
        'from_me' => 'boolean',
        'is_internal' => 'boolean',
        'message_timestamp' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CrmConversation::class , 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}