<?php

namespace App\Http\Controllers;

use App\Events\CrmLeadUpdated;
use App\Models\CrmLead;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CrmTag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CrmController extends Controller
{
    public function index(Request $request)
    {
        $teamId = auth()->user()->currentTeam->id;
        $pipelineId = $request->query('pipeline_id');

        $pipeline = null;
        if ($pipelineId) {
            $pipeline = CrmPipeline::forTeam($teamId)->find($pipelineId);
        }

        if (!$pipeline) {
            $pipeline = CrmPipeline::forTeam($teamId)
                ->where('is_default', true)
                ->first() ?: CrmPipeline::forTeam($teamId)->first();
        }

        // Create default pipeline if none exists
        if (!$pipeline) {
            $pipeline = CrmPipeline::create([
                'team_id' => $teamId,
                'name' => 'Vendas',
                'is_default' => true,
            ]);

            $defaultStages = [
                ['name' => 'Novo Lead', 'color' => '#6366f1', 'sort_order' => 0],
                ['name' => 'Em Atendimento', 'color' => '#f59e0b', 'sort_order' => 1],
                ['name' => 'Ganhos', 'color' => '#22c55e', 'sort_order' => 2, 'is_win' => true],
                ['name' => 'Perdidos', 'color' => '#ef4444', 'sort_order' => 3, 'is_lost' => true],
            ];

            foreach ($defaultStages as $stage) {
                $pipeline->stages()->create($stage);
            }
        }

        $pipelines = CrmPipeline::forTeam($teamId)
            ->with(['stages' => function ($q) {
            $q->orderBy('sort_order')->withCount('leads');
        }])
            ->orderBy('sort_order')
            ->get();

        // For the Pipeline view, we only show leads for the current pipeline
        // For the Inbox view, we might need leads from ANY pipeline to show in the contact context
        $leads = CrmLead::forTeam($teamId)
            ->with(['contact', 'tags', 'responsibleUser', 'stage.pipeline'])
            ->orderBy('created_at', 'desc')
            ->get();

        $tags = CrmTag::forTeam($teamId)->get();
        $teamMembers = auth()->user()->currentTeam->allUsers();

        // WhatsApp instances for real-time status
        $whatsappInstances = \App\Models\WhatsappInstance::forTeam($teamId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Load ALL conversations with contact + last 50 messages (no HTTP needed in frontend)
        $conversations = \App\Models\CrmConversation::forTeam($teamId)
            ->with(['contact', 'instance', 'latestMessages' => function ($q) {
            $q->take(50);
        }])
            ->orderBy('last_message_at', 'desc')
            ->get()
            ->map(function ($conv) {
            $conv->messages_list = $conv->latestMessages->reverse()->values();
            unset($conv->latestMessages);
            return $conv;
        });

        // Pipeline Analytics Summary
        $stats = [
            'total_value' => $leads->sum('value'),
            'total_count' => $leads->count(),
            'avg_value' => $leads->avg('value') ?? 0,
            'by_status' => [
                'active' => $leads->where('status', 'active')->count(),
                'won' => $leads->where('status', 'won')->count(),
                'lost' => $leads->where('status', 'lost')->count(),
            ],
        ];

        return Inertia::render('Crm', [
            'pipelines' => $pipelines,
            'currentPipelineId' => $pipeline->id,
            'leads' => $leads,
            'tags' => $tags,
            'teamMembers' => $teamMembers,
            'teamId' => $teamId,
            'whatsappInstances' => $whatsappInstances,
            'conversations' => $conversations,
            'stats' => $stats,
        ]);
    }

    public function storeStage(Request $request, CrmPipeline $pipeline)
    {
        $request->validate(['name' => 'required|string|max:255', 'color' => 'nullable|string|max:7']);

        $pipeline->stages()->create([
            'name' => $request->name,
            'color' => $request->color ?? '#6366f1',
            'sort_order' => $pipeline->stages()->count(),
        ]);

        return back();
    }

    public function reorderStages(Request $request, CrmPipeline $pipeline)
    {
        $request->validate(['stages' => 'required|array']);

        foreach ($request->stages as $i => $stageId) {
            CrmStage::where('id', $stageId)->update(['sort_order' => $i]);
        }

        return back();
    }

    public function storePipeline(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);
        $teamId = auth()->user()->currentTeam->id;

        $pipeline = CrmPipeline::create([
            'team_id' => $teamId,
            'name' => $request->name,
            'sort_order' => CrmPipeline::forTeam($teamId)->count(),
        ]);

        // Default stages
        $pipeline->stages()->createMany([
            ['name' => 'New', 'color' => '#6366f1', 'sort_order' => 0],
            ['name' => 'In Progress', 'color' => '#f59e0b', 'sort_order' => 1],
            ['name' => 'Won', 'color' => '#22c55e', 'sort_order' => 2, 'is_win' => true],
            ['name' => 'Lost', 'color' => '#ef4444', 'sort_order' => 3, 'is_lost' => true],
        ]);

        return back();
    }

    public function updateStage(Request $request, CrmStage $stage)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'is_win' => 'boolean',
            'is_lost' => 'boolean',
        ]);

        $stage->update($request->only('name', 'color', 'is_win', 'is_lost'));

        return back();
    }

    public function destroyStage(CrmStage $stage)
    {
        // Check if there are leads in this stage
        if ($stage->leads()->exists()) {
            return back()->with('error', 'Não é possível excluir um estágio com leads ativos.');
        }

        $stage->delete();

        return back();
    }

    public function destroyPipeline(CrmPipeline $pipeline)
    {
        if ($pipeline->is_default) {
            return back()->with('error', 'Não é possível excluir o funil padrão.');
        }

        if ($pipeline->leads()->exists()) {
            return back()->with('error', 'Não é possível excluir um funil com leads ativos.');
        }

        $pipeline->delete();

        return back();
    }

    public function storeTag(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'nullable|string|max:7',
        ]);

        $teamId = auth()->user()->currentTeam->id;

        $tag = CrmTag::create([
            'team_id' => $teamId,
            'name' => $request->name,
            'color' => $request->color ?? '#6366f1',
        ]);

        return back();
    }
}