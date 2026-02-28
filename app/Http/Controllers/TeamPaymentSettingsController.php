<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TeamPaymentSettingsController extends Controller
{
    public function update(Request $request, Team $team)
    {
        Gate::forUser($request->user())->authorize('update', $team);

        $input = $request->all();

        Validator::make($input, [
            'mercado_pago' => ['nullable', 'array'],
            'mercado_pago.public_key' => ['nullable', 'string', 'max:255'],
            'mercado_pago.access_token' => ['nullable', 'string', 'max:255'],
            'payment_message' => ['nullable', 'string', 'max:1000'],
            'confirmation_message' => ['nullable', 'string', 'max:1000'],
        ])->validateWithBag('updatePaymentSettings');

        $team->forceFill([
            'payment_settings' => [
                'mercado_pago' => $input['mercado_pago'] ?? [],
                'payment_message' => $input['payment_message'] ?? null,
                'confirmation_message' => $input['confirmation_message'] ?? null,
            ],
        ])->save();

        return back()->with('status', 'payment-settings-updated');
    }

    public function connectMercadoPago(Request $request, Team $team)
    {
        Gate::forUser($request->user())->authorize('update', $team);

        $clientId = config('services.mercado_pago.client_id');

        if (!$clientId) {
            return back()->withErrors(['error' => 'MERCADO_PAGO_CLIENT_ID não configurado no servidor.']);
        }

        $redirectUri = route('teams.payment-settings.mercado-pago.callback');
        $state = bin2hex(random_bytes(16));

        session(['mp_auth_state' => $state, 'mp_auth_team' => $team->id]);

        // Using global .com URL and minimal parameters (removed platform_id)
        $authUrl = "https://auth.mercadopago.com/authorization?client_id={$clientId}&response_type=code&state={$state}&redirect_uri=" . urlencode($redirectUri);

        Log::info('[MP OAuth] Redirecting to:', ['url' => $authUrl]);

        return redirect($authUrl);
    }

    public function callbackMercadoPago(Request $request)
    {
        $state = $request->get('state');
        $code = $request->get('code');

        if (!$state || $state !== session('mp_auth_state')) {
            Log::warning('[MP OAuth] Invalid state or session expired', ['received' => $state, 'expected' => session('mp_auth_state')]);
            return redirect()->route('dashboard')->withErrors(['error' => 'Sessão expirada ou estado inválido.']);
        }

        $teamId = session('mp_auth_team');
        $team = Team::findOrFail($teamId);

        $response = Http::asForm()->post('https://api.mercadopago.com/oauth/token', [
            'client_id' => config('services.mercado_pago.client_id'),
            'client_secret' => config('services.mercado_pago.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => route('teams.payment-settings.mercado-pago.callback'),
        ]);

        if ($response->failed()) {
            Log::error('[MP OAuth] Token exchange failed', ['body' => $response->body()]);
            return redirect()->route('teams.show', $team)->withErrors(['error' => 'Falha ao conectar com Mercado Pago.']);
        }

        $data = $response->json();

        $settings = $team->payment_settings;
        $settings['mercado_pago'] = array_merge($settings['mercado_pago'] ?? [], [
            'access_token' => $data['access_token'],
            'public_key' => $data['public_key'],
            'user_id' => $data['user_id'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        $team->forceFill(['payment_settings' => $settings])->save();

        return redirect()->route('teams.show', $team)->with('status', 'mercado-pago-connected');
    }
}