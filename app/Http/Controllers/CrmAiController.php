<?php

namespace App\Http\Controllers;

use App\Models\CrmConversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class CrmAiController extends Controller
{
    public function summarize(CrmConversation $conversation)
    {
        $this->authorizeConversation($conversation);

        $messages = $conversation->messages()
            ->where('is_internal', '=', false)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->reverse();

        if ($messages->isEmpty()) {
            return response()->json(['summary' => 'Nenhuma mensagem para resumir.']);
        }

        $chatText = $messages->map(function ($m) {
            $sender = $m->from_me ? 'Nós' : 'Cliente';
            return "{$sender}: {$m->content}";
        })->implode("\n");

        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            return response()->json([
                'summary' => "Simulação de Resumo: O cliente entrou em contato para saber sobre status do pedido e demonstrou interesse em novos serviços. (Configure GEMINI_API_KEY para resumos reais)"
            ]);
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => "Por favor, resuma a seguinte conversa de WhatsApp entre uma empresa e um cliente de forma concisa e profissional:\n\n{$chatText}"]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                $summary = $response->json('candidates.0.content.parts.0.text') ?? 'Não foi possível gerar o resumo.';
                return response()->json(['summary' => $summary]);
            }

            return response()->json(['summary' => 'Erro ao chamar a API do Gemini.'], 500);
        }
        catch (\Exception $e) {
            return response()->json(['summary' => 'Erro interno ao processar resumo.'], 500);
        }
    }

    private function authorizeConversation(CrmConversation $conversation): void
    {
        if ($conversation->team_id !== auth()->user()->currentTeam->id) {
            abort(403);
        }
    }
}