<?php

namespace App\Events;

use App\Models\CrmLead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrmLeadUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $teamId,
        public array $lead,
        )
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('team.' . $this->teamId . '.crm')];
    }

    public function broadcastAs(): string
    {
        return 'lead.updated';
    }

    public function broadcastWith(): array
    {
        return ['lead' => $this->lead];
    }
}