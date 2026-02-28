<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    protected $messageId;
    protected $conversationId;
    protected $type;
    protected $content;
    protected $filePath;
    protected $fileMime;
    protected $fileOriginalName;
    protected $fullMediaUrl;

    /**
     * Create a new job instance.
     */
    public function __construct($messageId, $conversationId, $type, $content, $filePath, $fileMime, $fileOriginalName, $fullMediaUrl)
    {
        $this->messageId = $messageId;
        $this->conversationId = $conversationId;
        $this->type = $type;
        $this->content = $content;
        $this->filePath = $filePath;
        $this->fileMime = $fileMime;
        $this->fileOriginalName = $fileOriginalName;
        $this->fullMediaUrl = $fullMediaUrl;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $message = \App\Models\CrmMessage::find($this->messageId);
        $conversation = \App\Models\CrmConversation::find($this->conversationId);

        if (!$message || !$conversation || !$conversation->whatsapp_instance_id) {
            return;
        }

        $instanceId = $conversation->whatsapp_instance_id;
        $waMessage = [];

        try {
            if ($this->type === 'image') {
                if ($this->filePath && file_exists($this->filePath)) {
                    $base64 = base64_encode(file_get_contents($this->filePath));
                    $waMessage = ['image' => ['base64' => $base64], 'caption' => $this->content];
                    \Log::info('[CRM Job] Base64 encoded image', ['size_mb' => round(strlen($base64) / 1024 / 1024, 2)]);
                }
                else {
                    $waMessage = ['image' => ['url' => $this->fullMediaUrl], 'caption' => $this->content];
                }
            }
            elseif ($this->type === 'document') {
                if ($this->filePath && file_exists($this->filePath)) {
                    $base64 = base64_encode(file_get_contents($this->filePath));
                    $waMessage = [
                        'document' => ['base64' => $base64],
                        'mimetype' => $this->fileMime,
                        'fileName' => $this->fileOriginalName,
                        'caption' => $this->content
                    ];
                    \Log::info('[CRM Job] Base64 encoded document', ['size_mb' => round(strlen($base64) / 1024 / 1024, 2)]);
                }
                else {
                    $waMessage = [
                        'document' => ['url' => $this->fullMediaUrl],
                        'mimetype' => 'application/octet-stream',
                        'fileName' => 'file',
                        'caption' => $this->content
                    ];
                }
            }
            else {
                $waMessage = ['text' => $this->content];
            }

            \Log::info('[CRM Job] Sending to Bun', [
                'instanceId' => $instanceId,
                'jid' => $conversation->jid,
                'type' => $this->type
            ]);

            $response = \Illuminate\Support\Facades\Http::timeout(30)->post('http://localhost:3001/send', [
                'instanceId' => $instanceId,
                'jid' => $conversation->jid,
                'message' => $waMessage,
            ]);

            \Log::info('[CRM Job] Bun server response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            if ($response->successful() && isset($response->json()['data']['key']['id'])) {
                $message->update([
                    'status' => 'sent',
                    'remote_id' => $response->json()['data']['key']['id']
                ]);
            }
            else {
                $message->update(['status' => 'failed']);
            }
        }
        catch (\Exception $e) {
            \Log::error('[CRM Job] Failed to send via Baileys', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $message->update(['status' => 'failed']);
        }

        // Broadcast the update so the UI changes to single checkmark instantly
        $message->refresh();
        $message->load('user');
        broadcast(new \App\Events\CrmMessageReceived($conversation->team_id, $conversation->id, $message->toArray()))->toOthers();
    }
}