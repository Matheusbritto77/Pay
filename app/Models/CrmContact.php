<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmContact extends Model
{
    protected $fillable = ['team_id', 'name', 'company', 'phone', 'email', 'avatar_url', 'metadata', 'is_group'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class , 'contact_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(CrmConversation::class , 'contact_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}