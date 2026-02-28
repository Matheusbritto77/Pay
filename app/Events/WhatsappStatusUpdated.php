<?php

namespace App\Events;

use App\Models\WhatsappInstance;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WhatsappStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $instanceId;
    public string $status;
    public ?string $qrCode;
    public ?string $phone;
    public int $teamId;

    public function __construct(WhatsappInstance $instance)
    {
        $this->instanceId = $instance->id;
        $this->status = $instance->status;
        $this->qrCode = $instance->qr_code;
        $this->phone = $instance->phone;
        $this->teamId = $instance->team_id;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('team.' . $this->teamId . '.whatsapp'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'status' => $this->status,
            'qr_code' => $this->qrCode,
            'phone' => $this->phone,
        ];
    }
}