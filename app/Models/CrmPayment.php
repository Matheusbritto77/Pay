<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmPayment extends Model
{
    protected $fillable = [
        'team_id',
        'contact_id',
        'lead_id',
        'description',
        'amount',
        'cost',
        'status',
        'external_id',
        'payment_url',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'cost' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class , 'contact_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class , 'lead_id');
    }
}