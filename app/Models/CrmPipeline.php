<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CrmPipeline extends Model
{
    protected $fillable = ['team_id', 'name', 'is_default', 'sort_order'];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function activities()
    {
        return $this->hasMany(CrmActivity::class , 'lead_id')->orderBy('created_at', 'desc');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(CrmStage::class , 'pipeline_id')->orderBy('order');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class , 'pipeline_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}