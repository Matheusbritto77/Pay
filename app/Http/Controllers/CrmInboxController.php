<?php

namespace App\Http\Controllers;

use App\Events\CrmConversationUpdated;
use App\Events\CrmMessageReceived;
use App\Models\CrmContact;
use App\Models\CrmConversation;
use App\Models\CrmMessage;
use App\Models\WhatsappInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CrmInboxController extends Controller
{
    public function markRead(CrmConversation $conversation)
    {
        if ($conversation->team_id !== auth()->user()->currentTeam->id) {
            abort(403);
        }

        $conversation->update(['unread_count' => 0]);

        try {
            Http::post('http://localhost:3001/read', [
                'instanceId' => $conversation->whatsapp_instance_id,
                'jid' => $conversation->jid,
            ]);
        }
        catch (\Exception $e) {
            Log::error('[CRM] Failed to notify Bun about read status', ['error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }

    public function loadHistory(Request $request, CrmConversation $conversation)
    {
        if ($conversation->team_id !== auth()->user()->currentTeam->id) {
            abort(403);
        }

        $lastMessageId = $request->query('before_id');
        $query = $conversation->messages()->orderBy('created_at', 'desc');

        if ($lastMessageId) {
            $lastMsg = CrmMessage::find($lastMessageId);
            if ($lastMsg) {
                $query->where('created_at', '<', $lastMsg->created_at);
            }
        }

        $messages = $query->limit(50)->get()->reverse()->values();
        return response()->json($messages);
    }

    public function getTemplates()
    {
        $teamId = auth()->user()->currentTeam->id;
        $templates = \App\Models\CrmTemplate::where('team_id', $teamId)->get();
        return response()->json($templates);
    }

    private function broadcastConversationUpdate($conversation)
    {
        $conversation->refresh();
        broadcast(new CrmConversationUpdated($conversation->team_id, $conversation->toArray()));
    }

    public function send(Request $request, CrmConversation $conversation)
    {
        if ($conversation->team_id !== auth()->user()->currentTeam->id) {
            abort(403);
        }

        Log::info('[CRM] send() called', [
            'hasFile' => $request->hasFile('file'),
            'content' => $request->input('content'),
            'is_internal' => $request->input('is_internal'),
            'all_keys' => array_keys($request->all()),
        ]);

        $request->validate([
            'content' => 'nullable|string',
            'is_internal' => 'nullable',
            'file' => 'nullable|file|max:10240',
        ]);

        $isInternal = $request->boolean('is_internal');

        // Check instance availability if not an internal note
        $instanceId = $conversation->whatsapp_instance_id;
        $instance = $instanceId ?WhatsappInstance::find($instanceId) : null;

        if (!$isInternal && (!$instance || $instance->status !== 'connected')) {
            $fallback = WhatsappInstance::where('team_id', $conversation->team_id)->where('status', 'connected')->first();
            if ($fallback) {
                $instanceId = $fallback->id;
                $conversation->update(['whatsapp_instance_id' => $instanceId]);
                $instance = $fallback;
            }
            else {
                return response()->json(['error' => 'Nenhuma conexão WhatsApp ativa disponível.'], 400);
            }
        }

        $file = $request->file('file');
        $mediaUrl = null;
        $type = 'text';
        $fileContents = null;
        $fileMime = null;
        $fileOriginalName = null;

        if ($file) {
            // IMPORTANT: Read file content BEFORE $file->move() — move() deletes the temp file!
            $fileContents = file_get_contents($file->getRealPath());
            $fileMime = $file->getClientMimeType();
            $fileOriginalName = $file->getClientOriginalName();

            $fileName = time() . '_' . $fileOriginalName;
            $file->move(storage_path('app/public/inbox_attachments'), $fileName);
            $mediaUrl = '/storage/inbox_attachments/' . $fileName;

            $type = str_contains($fileMime, 'image') ? 'image' : 'document';
        }

        $message = CrmMessage::create([
            'conversation_id' => $conversation->id,
            'from_me' => true,
            'type' => $type,
            'content' => $request->content,
            'media_url' => $mediaUrl,
            'status' => $isInternal ? 'delivered' : 'pending',
            'message_timestamp' => now(),
            'is_internal' => $isInternal,
            'user_id' => auth()->id(),
        ]);

        $conversation->update(['last_message_at' => now()]);

        $fullMediaUrl = $mediaUrl ? url($mediaUrl) : null;
        if ($fullMediaUrl && str_contains($fullMediaUrl, 'localhost') && !str_contains($fullMediaUrl, ':8000')) {
            $fullMediaUrl = str_replace('localhost', 'localhost:8000', $fullMediaUrl);
        }

        if (!$isInternal) {
            \App\Jobs\SendWhatsAppMessageJob::dispatch(
                $message->id,
                $conversation->id,
                $type,
                $request->content,
                isset($file) && $file ? storage_path('app/public/inbox_attachments/' . $fileName) : null,
                $fileMime ?? null,
                $fileOriginalName ?? null,
                $fullMediaUrl
            );
        }

        $message->load('user');
        broadcast(new CrmMessageReceived($conversation->team_id, $conversation->id, $message->toArray()))->toOthers();
        $this->broadcastConversationUpdate($conversation);

        return response()->json($message);
    }

    public function incomingMessage(Request $request)
    {
        $secret = $request->header('X-Whatsapp-Secret');
        if ($secret !== config('services.whatsapp.secret', 'whatsapp-erp-secret')) {
            abort(403);
        }

        $request->validate([
            'instanceId' => 'required|integer',
            'jid' => 'required|string',
            'message' => 'required|array',
        ]);

        $instance = WhatsappInstance::findOrFail($request->instanceId);
        $jid = $request->jid;
        $isGroup = str_ends_with($jid, '@g.us');
        $phone = explode('@', $jid)[0];
        $pushName = $request->input('message.pushName');
        // Clean up pushName: handle literal "Unknown", "uknow", or empty strings
        if (empty($pushName) || in_array(strtolower(trim($pushName)), ['unknown', 'uknow', 'null', 'undefined'])) {
            $pushName = null;
        }

        $name = $pushName ?: ($isGroup ? "Grupo: $phone" : $phone);

        $contact = CrmContact::firstOrCreate(
        ['team_id' => $instance->team_id, 'phone' => $phone],
        ['name' => $name, 'is_group' => $isGroup]
        );

        // Update name if it's currently a placeholder/phone and we got a valid pushName
        $currentNameLower = strtolower(trim($contact->name));
        $isPlaceholder = empty($contact->name) ||
            $contact->name === $contact->phone ||
            in_array($currentNameLower, ['unknown', 'uknow', 'null', 'undefined']);

        if ($pushName && $isPlaceholder) {
            $oldName = $contact->name;
            $contact->update(['name' => $pushName, 'is_group' => $isGroup]);

            // Also update any leads for this contact that have the old placeholder name
            $contact->leads()->where(function ($q) use ($oldName, $phone) {
                $q->where('name', $oldName)
                    ->orWhere('name', $phone)
                    ->orWhere('name', 'like', 'Unknown%')
                    ->orWhere('name', 'like', 'uknow%');
            })->update(['name' => $pushName]);
        }

        if ($contact->is_group !== $isGroup) {
            $contact->update(['is_group' => $isGroup]);
        }
        $conversation = CrmConversation::firstOrCreate(
        ['team_id' => $instance->team_id, 'jid' => $request->jid],
        ['contact_id' => $contact->id, 'whatsapp_instance_id' => $instance->id]
        );

        if ($conversation->whatsapp_instance_id !== $instance->id) {
            $conversation->update(['whatsapp_instance_id' => $instance->id]);
        }

        $msg = $request->input('message');
        $type = 'text';
        $content = $msg['text'] ?? '';

        if (isset($msg['imageMessage']))
            $type = 'image';
        elseif (isset($msg['audioMessage']))
            $type = 'audio';
        elseif (isset($msg['videoMessage']))
            $type = 'video';
        elseif (isset($msg['documentMessage']))
            $type = 'document';

        $remoteId = $msg['id'] ?? null;
        if ($remoteId && CrmMessage::where('remote_id', $remoteId)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        if ($msg['fromMe']) {
            $existing = CrmMessage::where('conversation_id', $conversation->id)
                ->where('from_me', true)
                ->where('content', $content)
                ->whereNull('remote_id')
                ->where('created_at', '>', now()->subSeconds(20))
                ->first();

            if ($existing) {
                $existing->update(['remote_id' => $remoteId, 'status' => 'delivered']);
                return response()->json(['ok' => true, 'updated' => true]);
            }
        }

        $crmMessage = CrmMessage::create([
            'conversation_id' => $conversation->id,
            'from_me' => $msg['fromMe'] ?? false,
            'type' => $request->input('message.type', $type),
            'content' => $content,
            'media_url' => $request->input('message.mediaUrl'),
            'status' => 'delivered',
            'remote_id' => $remoteId,
            'message_timestamp' => now(),
        ]);

        broadcast(new CrmMessageReceived($conversation->team_id, $conversation->id, $crmMessage->toArray()));
        $this->broadcastConversationUpdate($conversation);

        return response()->json(['ok' => true]);
    }
}