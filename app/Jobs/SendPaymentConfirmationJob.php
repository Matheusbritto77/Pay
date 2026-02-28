<?php

namespace App\Jobs;

use App\Models\CrmConversation;
use App\Models\CrmMessage;
use App\Models\CrmPayment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPaymentConfirmationJob implements ShouldQueue
{
    use Queueable;

    protected $paymentId;

    /**
     * Create a new job instance.
     */
    public function __construct($paymentId)
    {
        $this->paymentId = $paymentId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $payment = CrmPayment::with(['contact', 'team'])->find($this->paymentId);

        if (!$payment || !$payment->contact) {
            Log::error('[Confirmation Job] Payment or Contact not found', ['payment_id' => $this->paymentId]);
            return;
        }

        $contact = $payment->contact;
        $team = $payment->team;

        // Find the most recent conversation with this contact
        $conversation = CrmConversation::where('contact_id', $contact->id)
            ->where('team_id', $team->id)
            ->orderBy('updated_at', 'desc')
            ->first();

        // If no conversation exists, we might need a default instance
        if (!$conversation) {
            $instance = $team->whatsappInstances()->where('status', 'connected')->first();
            if (!$instance) {
                Log::error('[Confirmation Job] No connected WhatsApp instance found for team', ['team_id' => $team->id]);
                return;
            }

            $conversation = CrmConversation::create([
                'team_id' => $team->id,
                'contact_id' => $contact->id,
                'whatsapp_instance_id' => $instance->id,
                'jid' => $contact->phone . '@s.whatsapp.net',
                'last_message_at' => now(),
            ]);
        }

        $amountFormatted = 'R$ ' . number_format($payment->amount, 2, ',', '.');
        $defaultTemplate = "✅ *Pagamento Confirmado!*\n\nRecebemos o seu pagamento referente a: *{description}*\nValor: *{amount}*\n\nObrigado!";
        $template = $team->payment_settings['confirmation_message'] ?? $defaultTemplate;

        $messageText = str_replace(
        ['{description}', '{amount}'],
        [$payment->description, $amountFormatted],
            $template
        );

        // Create the message record
        $message = CrmMessage::create([
            'conversation_id' => $conversation->id,
            'content' => $messageText,
            'type' => 'text',
            'from_me' => true,
            'status' => 'pending',
            'is_internal' => false,
        ]);

        // Dispatch the actual sending job
        SendWhatsAppMessageJob::dispatch(
            $message->id,
            $conversation->id,
            'text',
            $messageText,
            null, // filePath
            null, // fileMime
            null, // fileOriginalName
            null // fullMediaUrl
        );

        Log::info('[Confirmation Job] Confirmation message queued', [
            'payment_id' => $payment->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id
        ]);
    }
}