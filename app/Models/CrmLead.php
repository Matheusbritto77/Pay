<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmLead extends Model
{
    protected $fillable = [
        'team_id', 'contact_id', 'pipeline_id', 'stage_id',
        'responsible_user_id', 'name', 'value', 'status', 'source', 'loss_reason', 'closed_at',
    ];

    public function activities()
    {
        return $this->hasMany(CrmActivity::class , 'lead_id')->orderBy('created_at', 'desc');
    }

    protected $casts = [
        'value' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class , 'contact_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class , 'pipeline_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CrmStage::class , 'stage_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class , 'responsible_user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class , 'lead_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CrmNote::class , 'lead_id')->orderBy('created_at', 'desc');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CrmTag::class , 'crm_lead_tag', 'lead_id', 'tag_id');
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}