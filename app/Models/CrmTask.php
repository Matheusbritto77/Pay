<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmTask extends Model
{
    protected $fillable = [
        'team_id', 'lead_id', 'contact_id', 'user_id',
        'title', 'description', 'due_date', 'completed_at', 'type',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class , 'lead_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class , 'contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopePending($query)
    {
        return $query->whereNull('completed_at');
    }
}