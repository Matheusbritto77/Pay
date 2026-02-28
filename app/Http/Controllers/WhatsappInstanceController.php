<?php

namespace App\Http\Controllers;

use App\Events\WhatsappStatusUpdated;
use App\Models\WhatsappInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class WhatsappInstanceController extends Controller
{
    /**
     * Display the connections page.
     */
    public function index()
    {
        $teamId = auth()->user()->currentTeam->id;

        $instances = WhatsappInstance::forTeam($teamId)
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Connections', [
            'instances' => $instances,
            'teamId' => $teamId,
        ]);
    }

    /**
     * Create a new WhatsApp instance.
     */
    public function store(Request $request)
    {
        $teamId = auth()->user()->currentTeam->id;

        // Auto-generate unique session name
        $count = WhatsappInstance::forTeam($teamId)->count() + 1;
        $hash = substr(md5(uniqid()), 0, 6);
        $name = "session-{$count}-{$hash}";

        $instance = WhatsappInstance::create([
            'team_id' => $teamId,
            'name' => $name,
            'status' => 'disconnected',
        ]);

        return back()->with('success', 'Instance created.');
    }

    /**
     * Connect an instance (trigger Baileys session).
     */
    public function connect(WhatsappInstance $instance)
    {
        $this->authorize($instance);

        $instance->update(['status' => 'connecting']);
        event(new WhatsappStatusUpdated($instance));

        // Call the Bun/Baileys server to start connection
        try {
            Http::timeout(5)->post('http://localhost:3001/connect', [
                'instanceId' => $instance->id,
                'teamId' => $instance->team_id,
            ]);
        }
        catch (\Exception $e) {
            $instance->update(['status' => 'disconnected']);
            event(new WhatsappStatusUpdated($instance));
            return back()->withErrors(['server' => 'WhatsApp server is not running. Run: php artisan whatsapp:serve']);
        }

        return back();
    }

    /**
     * Disconnect an instance.
     */
    public function disconnect(WhatsappInstance $instance)
    {
        $this->authorize($instance);

        try {
            Http::timeout(5)->post('http://localhost:3001/disconnect', [
                'instanceId' => $instance->id,
            ]);
        }
        catch (\Exception $e) {
        // Server might be down, just update status
        }

        $instance->update([
            'status' => 'disconnected',
            'qr_code' => null,
        ]);
        event(new WhatsappStatusUpdated($instance));

        return back();
    }

    /**
     * Delete an instance and cleanup.
     */
    public function destroy(WhatsappInstance $instance)
    {
        $this->authorize($instance);

        try {
            Http::timeout(5)->post('http://localhost:3001/delete', [
                'instanceId' => $instance->id,
                'teamId' => $instance->team_id,
            ]);
        }
        catch (\Exception $e) {
        // Server might be down, continue with deletion
        }

        $instance->delete();

        return back()->with('success', 'Instance deleted.');
    }

    /**
     * Webhook endpoint for Bun server status updates.
     */
    public function webhook(Request $request)
    {
        $request->validate([
            'instanceId' => 'required|integer',
            'status' => 'required|string',
        ]);

        // Verify shared secret
        $secret = $request->header('X-Whatsapp-Secret');
        if ($secret !== config('services.whatsapp.secret', 'whatsapp-erp-secret')) {
            abort(403, 'Invalid secret');
        }

        $instance = WhatsappInstance::findOrFail($request->instanceId);

        $updateData = ['status' => $request->status];

        if ($request->has('qr_code')) {
            $updateData['qr_code'] = $request->qr_code;
        }
        if ($request->has('phone')) {
            $updateData['phone'] = $request->phone;
        }
        if ($request->status === 'connected') {
            $updateData['last_seen_at'] = now();
            $updateData['qr_code'] = null;
        }

        $instance->update($updateData);
        event(new WhatsappStatusUpdated($instance));

        return response()->json(['ok' => true]);
    }

    /**
     * Authorize that the user owns this instance via their team.
     */
    private function authorize(WhatsappInstance $instance): void
    {
        if ($instance->team_id !== auth()->user()->currentTeam->id) {
            abort(403, 'Unauthorized');
        }
    }
}