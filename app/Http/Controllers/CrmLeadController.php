<?php

namespace App\Http\Controllers;

use App\Events\CrmLeadUpdated;
use App\Models\CrmLead;
use App\Models\CrmNote;
use App\Models\CrmTask;
use App\Models\CrmContact;
use Illuminate\Http\Request;

class CrmLeadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'stage_id' => 'required|exists:crm_stages,id',
            'pipeline_id' => 'required|exists:crm_pipelines,id',
            'value' => 'nullable|numeric|min:0',
            'contact_id' => 'nullable|exists:crm_contacts,id',
            'responsible_user_id' => 'nullable|exists:users,id',
            'source' => 'nullable|string|max:100',
        ]);

        $teamId = auth()->user()->currentTeam->id;

        $lead = CrmLead::create([
            'team_id' => $teamId,
            'name' => $request->name,
            'stage_id' => $request->stage_id,
            'pipeline_id' => $request->pipeline_id,
            'value' => $request->value ?? 0,
            'contact_id' => $request->contact_id,
            'responsible_user_id' => $request->responsible_user_id,
            'source' => $request->source ?? 'manual',
        ]);

        $this->createActivity($lead, 'lead_created', "Lead criado via {$lead->source}");

        $lead->load(['contact', 'tags', 'responsibleUser', 'stage']);

        event(new CrmLeadUpdated($teamId, $lead->toArray()));

        return back();
    }

    public function update(Request $request, CrmLead $lead)
    {
        $this->authorizeLead($lead);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'value' => 'sometimes|numeric|min:0',
            'stage_id' => 'sometimes|exists:crm_stages,id',
            'responsible_user_id' => 'nullable|exists:users,id',
            'status' => 'sometimes|in:active,won,lost',
            'source' => 'sometimes|string|max:100',
            'loss_reason' => 'nullable|string|max:255',
        ]);

        $oldStageId = $lead->stage_id;
        $oldValue = $lead->value;
        $oldStatus = $lead->status;

        $data = $request->only(['name', 'value', 'stage_id', 'responsible_user_id', 'status', 'source', 'loss_reason']);

        if (isset($data['status']) && in_array($data['status'], ['won', 'lost'])) {
            $data['closed_at'] = now();
        }

        $lead->update($data);

        // Log significant changes
        if (isset($data['stage_id']) && $data['stage_id'] != $oldStageId) {
            $this->createActivity($lead, 'stage_change', 'Alterou etapa do lead', ['old' => $oldStageId, 'new' => $data['stage_id']]);
        }
        if (isset($data['value']) && $data['value'] != $oldValue) {
            $this->createActivity($lead, 'value_change', "Alterou valor: " . number_format($data['value'], 2), ['old' => $oldValue, 'new' => $data['value']]);
        }
        if (isset($data['status']) && $data['status'] != $oldStatus) {
            $this->createActivity($lead, 'status_change', "Status alterado para {$data['status']}", ['old' => $oldStatus, 'new' => $data['status']]);
        }

        $lead->load(['contact', 'tags', 'responsibleUser', 'stage']);

        event(new CrmLeadUpdated($lead->team_id, $lead->toArray()));

        return back();
    }

    public function moveStage(Request $request, CrmLead $lead)
    {
        $this->authorizeLead($lead);

        $request->validate(['stage_id' => 'required|exists:crm_stages,id']);

        $oldStageId = $lead->stage_id;
        $lead->update(['stage_id' => $request->stage_id]);

        $this->createActivity($lead, 'stage_change', 'Moveu para nova etapa', ['old' => $oldStageId, 'new' => $request->stage_id]);

        $lead->load(['contact', 'tags', 'responsibleUser', 'stage']);

        event(new CrmLeadUpdated($lead->team_id, $lead->toArray()));

        return back();
    }

    public function destroy(CrmLead $lead)
    {
        $this->authorizeLead($lead);
        $lead->delete();
        return back();
    }

    public function show(CrmLead $lead)
    {
        $this->authorizeLead($lead);

        $lead->load([
            'contact',
            'tags',
            'responsibleUser',
            'stage',
            'notes.user',
            'tasks.user',
            'activities.user',
        ]);

        return response()->json($lead);
    }

    // Notes
    public function storeNote(Request $request, CrmLead $lead)
    {
        $this->authorizeLead($lead);
        $request->validate(['content' => 'required|string']);

        $note = $lead->notes()->create([
            'user_id' => auth()->id(),
            'content' => $request->content,
        ]);
        $note->load('user');

        return response()->json($note);
    }

    // Tasks
    public function storeTask(Request $request, CrmLead $lead)
    {
        $this->authorizeLead($lead);

        $request->validate([
            'title' => 'required|string|max:255',
            'due_date' => 'nullable|date',
            'type' => 'sometimes|in:call,meeting,task,email',
        ]);

        $task = $lead->tasks()->create([
            'team_id' => $lead->team_id,
            'user_id' => auth()->id(),
            'title' => $request->title,
            'due_date' => $request->due_date,
            'type' => $request->type ?? 'task',
        ]);
        $task->load('user');

        return response()->json($task);
    }

    public function completeTask(CrmTask $task)
    {
        $task->update(['completed_at' => now()]);
        return response()->json(['ok' => true]);
    }

    // Tags
    public function syncTags(Request $request, CrmLead $lead)
    {
        $this->authorizeLead($lead);
        $request->validate(['tag_ids' => 'array']);
        $lead->tags()->sync($request->tag_ids ?? []);
        return response()->json(['ok' => true]);
    }

    private function authorizeLead(CrmLead $lead): void
    {
        if ($lead->team_id !== auth()->user()->currentTeam->id) {
            abort(403);
        }
    }

    private function createActivity(CrmLead $lead, string $type, string $description, array $properties = []): void
    {
        $lead->activities()->create([
            'team_id' => $lead->team_id,
            'user_id' => auth()->id(),
            'type' => $type,
            'description' => $description,
            'properties' => $properties,
        ]);
    }
}