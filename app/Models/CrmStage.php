<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmStage extends Model
{
    protected $fillable = ['pipeline_id', 'name', 'color', 'sort_order', 'is_win', 'is_lost'];

    protected $casts = [
        'is_win' => 'boolean',
        'is_lost' => 'boolean',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class , 'pipeline_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class , 'stage_id');
    }
}