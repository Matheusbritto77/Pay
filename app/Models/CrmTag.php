<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrmTag extends Model
{
    protected $fillable = ['team_id', 'name', 'color'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(CrmLead::class , 'crm_lead_tag', 'tag_id', 'lead_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}