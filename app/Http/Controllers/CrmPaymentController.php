<?php

namespace App\Http\Controllers;

use App\Models\CrmContact;
use App\Models\CrmConversation;
use App\Models\CrmLead;
use App\Models\CrmPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class CrmPaymentController extends Controller
{
    public function index(Request $request)
    {
        $team = auth()->user()->currentTeam;
        $query = CrmPayment::where('team_id', $team->id)
            ->with(['contact', 'lead'])
            ->orderBy('created_at', 'desc');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                }
                );
            });
        }

        if ($request->has('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $payments = $query->paginate(20)->withQueryString();

        return \Inertia\Inertia::render('Crm/Payments', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'status'])
        ]);
    }

    public function resend(CrmPayment $payment)
    {
        Gate::authorize('update', $payment->team);

        $team = $payment->team;
        $settings = $team->payment_settings;

        $amountFormatted = 'R$ ' . number_format($payment->amount, 2, ',', '.');
        $defaultTemplate = "Olá! Segue o link para pagamento ref. {description}:\n\n{link}";
        $template = $settings['payment_message'] ?? $defaultTemplate;

        $messageText = str_replace(
        ['{description}', '{amount}', '{link}'],
        [$payment->description, $amountFormatted, $payment->payment_url],
            $template
        );

        // Find or create conversation
        $conversation = CrmConversation::where('contact_id', $payment->contact_id)
            ->where('team_id', $team->id)
            ->orderBy('updated_at', 'desc')
            ->first();

        if (!$conversation) {
            $instance = $team->whatsappInstances()->where('status', 'connected')->first();
            if (!$instance) {
                return back()->withErrors(['error' => 'Nenhuma instância conectada para enviar a mensagem.']);
            }

            $conversation = CrmConversation::create([
                'team_id' => $team->id,
                'contact_id' => $payment->contact_id,
                'whatsapp_instance_id' => $instance->id,
                'jid' => $payment->contact->phone . '@s.whatsapp.net',
                'last_message_at' => now(),
            ]);
        }

        // Create the message record
        $message = \App\Models\CrmMessage::create([
            'conversation_id' => $conversation->id,
            'content' => $messageText,
            'type' => 'text',
            'from_me' => true,
            'status' => 'pending',
            'is_internal' => false,
            'user_id' => auth()->id(),
        ]);

        // Dispatch the actual sending job
        \App\Jobs\SendWhatsAppMessageJob::dispatch(
            $message->id,
            $conversation->id,
            'text',
            $messageText,
            null, null, null, null
        );

        return back()->with('status', 'Cobrança enviada com sucesso!');
    }

    public function store(Request $request)
    {
        $request->validate([
            'contact_id' => 'required|exists:crm_contacts,id',
            'lead_id' => 'nullable|exists:crm_leads,id',
            'amount' => 'required|numeric|min:0.01',
            'cost' => 'nullable|numeric|min:0',
            'description' => 'required|string|max:255',
            'conversation_id' => 'nullable|exists:crm_conversations,id',
        ]);

        $team = auth()->user()->currentTeam;
        $settings = $team->payment_settings;

        if (!isset($settings['mercado_pago']['access_token'])) {
            return response()->json(['error' => 'Configurações do Mercado Pago não encontradas no time.'], 400);
        }

        $accessToken = $settings['mercado_pago']['access_token'];
        $contact = CrmContact::findOrFail($request->contact_id);

        try {
            $response = Http::withToken($accessToken)
                ->post('https://api.mercadopago.com/checkout/preferences', [
                'items' => [
                    [
                        'title' => $request->description,
                        'quantity' => 1,
                        'unit_price' => (float)$request->amount,
                        'currency_id' => 'BRL',
                    ]
                ],
                'payer' => [
                    'name' => $contact->name,
                    'email' => $contact->email ?: 'cliente@exemplo.com',
                    'phone' => [
                        'number' => $contact->phone
                    ]
                ],
                'notification_url' => str_contains(config('app.url'), 'localhost') || str_contains(config('app.url'), '127.0.0.1') ? null : route('api.payments.webhook', ['team' => $team->id]),
                'external_reference' => (string)microtime(true),
            ]);

            if ($response->failed()) {
                Log::error('[CRM] Mercado Pago Preference Error', ['body' => $response->body()]);
                return response()->json(['error' => 'Falha ao gerar link no Mercado Pago: ' . ($response->json()['message'] ?? 'Erro desconhecido')], 500);
            }

            $data = $response->json();
            $paymentUrl = $data['init_point'];
            $externalId = $data['id'];

            $payment = CrmPayment::create([
                'team_id' => $team->id,
                'contact_id' => $contact->id,
                'lead_id' => $request->lead_id,
                'description' => $request->description,
                'amount' => $request->amount,
                'cost' => $request->cost,
                'status' => 'pending',
                'external_id' => $externalId,
                'payment_url' => $paymentUrl,
                'metadata' => $data,
            ]);

            $amountFormatted = 'R$ ' . number_format($request->amount, 2, ',', '.');
            $defaultTemplate = "Olá! Segue o link para pagamento ref. {description}:\n\n{link}";
            $template = $settings['payment_message'] ?? $defaultTemplate;

            $message = str_replace(
            ['{description}', '{amount}', '{link}'],
            [$request->description, $amountFormatted, $paymentUrl],
                $template
            );

            return response()->json([
                'payment' => $payment,
                'payment_url' => $paymentUrl,
                'message' => $message
            ]);

        }
        catch (\Exception $e) {
            Log::error('[CRM] Payment Creation Exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Erro interno ao processar pagamento.'], 500);
        }
    }

    public function webhook(Request $request, $teamId = null)
    {
        Log::info('[CRM] Payment Webhook received', [
            'team_id' => $teamId,
            'data' => $request->all()
        ]);

        $type = $request->input('type') ?? $request->input('topic');
        $id = $request->input('data.id') ?? $request->input('id');

        if ($type !== 'payment' || !$id) {
            return response()->json(['status' => 'ignored']);
        }

        // We need the team to get the access token
        $team = \App\Models\Team::find($teamId);
        if (!$team || !isset($team->payment_settings['mercado_pago']['access_token'])) {
            Log::error('[CRM Webhook] Team or token not found', ['team_id' => $teamId]);
            return response()->json(['error' => 'Team or token not found'], 400);
        }

        $accessToken = $team->payment_settings['mercado_pago']['access_token'];

        try {
            $response = Http::withToken($accessToken)
                ->get("https://api.mercadopago.com/v1/payments/{$id}");

            if ($response->failed()) {
                Log::error('[CRM Webhook] Failed to fetch payment details', ['id' => $id, 'error' => $response->body()]);
                return response()->json(['error' => 'Failed to fetch payment details'], 500);
            }

            $paymentData = $response->json();
            $status = $paymentData['status'] ?? null;
            $preferenceId = $paymentData['preference_id'] ?? null;

            if ($status === 'approved' && $preferenceId) {
                $payment = CrmPayment::where('external_id', $preferenceId)->first();

                if ($payment && $payment->status !== 'paid') {
                    $payment->update([
                        'status' => 'paid',
                        'metadata' => array_merge($payment->metadata ?? [], ['payment_details' => $paymentData])
                    ]);

                    // Update Lead if exists
                    if ($payment->lead_id) {
                        $payment->lead->update(['status' => 'won']);
                    }

                    // Dispatch Confirmation Job
                    \App\Jobs\SendPaymentConfirmationJob::dispatch($payment->id);
                }
            }

            return response()->json(['status' => 'ok']);
        }
        catch (\Exception $e) {
            Log::error('[CRM Webhook] Exception', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'internal error'], 500);
        }
    }
}