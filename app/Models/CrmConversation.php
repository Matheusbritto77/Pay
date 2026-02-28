<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmConversation extends Model
{
    protected $fillable = [
        'team_id', 'contact_id', 'whatsapp_instance_id',
        'jid', 'last_message_at', 'unread_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class , 'contact_id');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WhatsappInstance::class , 'whatsapp_instance_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CrmMessage::class , 'conversation_id')->orderBy('created_at', 'asc');
    }

    public function latestMessages(): HasMany
    {
        return $this->hasMany(CrmMessage::class , 'conversation_id')->orderBy('created_at', 'desc');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}