<script setup>
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    stagesWithLeads: Array,
    pipelineId: Number,
});

const emit = defineEmits(['open-lead', 'move-lead', 'update-stage', 'delete-stage', 'add-stage', 'reorder-stages']);

const formatValue = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);

function timeAgo(date) {
    if (!date) return '';
    const diff = Math.floor((new Date() - new Date(date)) / 1000);
    if (diff < 60) return 'agora';
    if (diff < 3600) return `${Math.floor(diff/60)}m`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h`;
    return `${Math.floor(diff/86400)}d`;
}

function isSlaViolated(date) {
    if (!date) return false;
    const diff = (new Date() - new Date(date)) / 1000;
    return diff > 86400; // 24 hours
}

// Stage Management
const editingStageId = ref(null);
const stageEditForm = ref({ name: '', color: '' });
const showAddStage = ref(false);
const newStageForm = ref({ name: '', color: '#6366f1' });

function startEditingStage(stage) {
    editingStageId.value = stage.id;
    stageEditForm.value = { name: stage.name, color: stage.color };
}

function saveStageEdit() {
    emit('update-stage', { id: editingStageId.value, ...stageEditForm.value });
    editingStageId.value = null;
}

function onAddStage() {
    if (!newStageForm.value.name) return;
    emit('add-stage', { pipeline_id: props.pipelineId, ...newStageForm.value });
    newStageForm.value = { name: '', color: '#6366f1' };
    showAddStage.value = false;
}

// Drag and Drop (Leads)
let draggingLeadId = null;
function onDragStart(e, leadId) { 
    e.stopPropagation();
    e.dataTransfer.effectAllowed = 'move'; 
    e.dataTransfer.setData('type', 'lead');
    draggingLeadId = leadId; 
}

function onDrop(e, stageId) {
    const type = e.dataTransfer.getData('type');
    if (type === 'lead' && draggingLeadId) {
        emit('move-lead', { leadId: draggingLeadId, stageId });
        draggingLeadId = null;
    } else if (type === 'stage') {
        onStageDrop(stageId);
    }
}

// Drag and Drop (Stages)
let draggingStageId = null;
function onStageDragStart(e, stageId) {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('type', 'stage');
    draggingStageId = stageId;
}

function onStageDrop(targetStageId) {
    if (draggingStageId && draggingStageId !== targetStageId) {
        const stageIds = props.stagesWithLeads.map(s => s.id);
        const fromIdx = stageIds.indexOf(draggingStageId);
        const toIdx = stageIds.indexOf(targetStageId);
        stageIds.splice(toIdx, 0, stageIds.splice(fromIdx, 1)[0]);
        emit('reorder-stages', stageIds);
    }
    draggingStageId = null;
}
</script>

<template>
    <div class="h-full flex gap-1 p-6 overflow-x-auto items-stretch bg-white dark:bg-slate-950 pb-10">
        <div v-for="stage in stagesWithLeads" :key="stage.id"
            class="w-80 shrink-0 flex flex-col bg-slate-50 dark:bg-slate-900/50 rounded-[32px] border border-gray-200 dark:border-slate-800/60 mr-4 transition-all"
            @dragover.prevent @drop="onDrop($event, stage.id)">
            
            <!-- Stage Header (Draggable for stage reordering) -->
            <div class="p-5 flex items-center justify-between group/header cursor-grab active:cursor-grabbing"
                draggable="true" @dragstart="onStageDragStart($event, stage.id)">
                <div v-if="editingStageId === stage.id" class="flex items-center gap-2 w-full" @dragstart.prevent.stop>
                    <input v-model="stageEditForm.color" type="color" class="w-6 h-6 rounded-lg border-none p-0 cursor-pointer overflow-hidden shadow-sm" />
                    <input v-model="stageEditForm.name" @keyup.enter="saveStageEdit" @blur="saveStageEdit" class="flex-1 bg-white dark:bg-slate-800 border-none rounded-xl text-xs font-black p-2 h-8 focus:ring-2 focus:ring-indigo-500 shadow-inner" auto-focus />
                </div>
                <div v-else class="flex items-center gap-2 w-full">
                    <div class="w-3 h-3 rounded-full shadow-lg" :style="{ backgroundColor: stage.color }"></div>
                    <span @dblclick="startEditingStage(stage)" class="text-[11px] font-black uppercase tracking-widest text-slate-800 dark:text-slate-200 truncate flex-1">{{ stage.name }}</span>
                    <span class="text-[10px] font-black text-slate-400 bg-white dark:bg-slate-800 px-2.5 py-1 rounded-full border border-gray-100 dark:border-slate-700 shadow-sm">{{ stage.leads.length }}</span>
                    
                    <div class="flex opacity-0 group-hover/header:opacity-100 transition-opacity">
                        <button @click.stop="startEditingStage(stage)" class="p-1 hover:text-indigo-600 dark:hover:text-indigo-400 text-slate-400 transition-all"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg></button>
                        <button v-if="!stage.is_win && !stage.is_lost && stage.leads.length === 0" @click.stop="emit('delete-stage', stage.id)" class="p-1 hover:text-red-500 text-slate-400 transition-all"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                    </div>
                </div>
            </div>

            <!-- Leads Container -->
            <div class="flex-1 overflow-y-auto px-4 pb-4 space-y-4 custom-scrollbar min-h-[50px]">
                <div v-for="lead in stage.leads" :key="lead.id"
                    draggable="true" @dragstart="onDragStart($event, lead.id)" @click="emit('open-lead', lead)"
                    class="bg-white dark:bg-slate-800 p-5 rounded-[28px] shadow-sm border border-gray-100 dark:border-slate-700/50 cursor-pointer hover:shadow-2xl hover:-translate-y-1 transition-all group relative overflow-hidden">
                    
                    <div class="absolute top-0 left-0 w-1.5 h-full opacity-60" :style="{ backgroundColor: stage.color }"></div>

                    <div class="flex justify-between items-start gap-3 mb-3">
                        <h4 class="text-sm font-black text-slate-800 dark:text-white group-hover:text-indigo-600 transition-colors leading-snug">{{ lead.name }}</h4>
                        <div class="flex flex-col items-end shrink-0">
                            <span class="text-[11px] font-black text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-2 py-0.5 rounded-lg">{{ formatValue(lead.value) }}</span>
                        </div>
                    </div>

                    <div v-if="lead.contact" class="flex flex-col gap-1.5 mb-4 border-l-2 border-slate-100 dark:border-slate-800 pl-3">
                        <div class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-300">
                            <div class="w-5 h-5 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-[10px] font-black">{{ lead.contact.name?.[0] }}</div>
                            {{ lead.contact.name }}
                        </div>
                        <div v-if="lead.contact.company" class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-tighter">
                            <svg class="w-3 h-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            {{ lead.contact.company }}
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-100 dark:border-slate-800/60">
                        <div class="flex gap-1.5 overflow-hidden items-center">
                            <span v-for="tag in lead.tags?.slice(0,2)" :key="tag.id" class="px-2 py-0.5 rounded-lg text-[9px] font-black text-white shrink-0" :style="{ backgroundColor: tag.color }">{{ tag.name }}</span>
                            <!-- SLA Pulse Indicator -->
                            <div v-if="isSlaViolated(lead.updated_at)" class="flex items-center gap-1 bg-red-50 dark:bg-red-500/10 px-2 py-0.5 rounded-lg border border-red-100 dark:border-red-500/20 animate-pulse" title="Sem atualização há mais de 24h">
                                <div class="w-1 h-1 rounded-full bg-red-500"></div>
                                <span class="text-[8px] font-black text-red-600 uppercase">Atrasado</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-1.5 opacity-60">
                                <span v-if="lead.notes_count > 0" class="flex items-center gap-0.5 text-[9px] font-black text-slate-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>{{ lead.notes_count }}</span>
                                <span v-if="lead.tasks_count > 0" class="flex items-center gap-0.5 text-[9px] font-black text-slate-500"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>{{ lead.tasks_count }}</span>
                            </div>
                            <span class="text-[9px] text-slate-400 font-black uppercase tracking-tighter">{{ timeAgo(lead.updated_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Empty State within stage -->
                <div v-if="stage.leads.length === 0" class="h-32 rounded-[28px] border-2 border-dashed border-gray-200 dark:border-slate-800/60 flex flex-col items-center justify-center text-slate-300 dark:text-slate-700">
                    <svg class="w-8 h-8 mb-1 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-[9px] font-black uppercase tracking-widest opacity-40">Arraste um lead</span>
                </div>
            </div>

            <!-- Stage Footer Summary -->
            <div class="p-5 border-t border-gray-200/50 dark:border-slate-800/40 bg-white/40 dark:bg-slate-900/40 rounded-b-[32px]">
                <div class="flex justify-between items-center text-[10px] font-black text-slate-400 uppercase tracking-widest">
                    <span>Valor em Estágio</span>
                    <span class="text-indigo-600 dark:text-indigo-400">{{ formatValue(stage.leads.reduce((s, l) => s + Number(l.value || 0), 0)) }}</span>
                </div>
            </div>
        </div>

        <!-- Add Stage Column -->
        <div class="w-80 shrink-0 flex flex-col">
            <button v-if="!showAddStage" @click="showAddStage = true" class="h-[72px] rounded-[32px] border-2 border-dashed border-gray-200 dark:border-slate-800/60 hover:border-indigo-500/50 hover:bg-indigo-500/5 transition-all flex items-center justify-center gap-3 group">
                <div class="w-8 h-8 bg-indigo-500/10 text-indigo-500 rounded-xl flex items-center justify-center group-hover:bg-indigo-500 group-hover:text-white transition-all shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M12 4v16m8-8H4"/></svg>
                </div>
                <span class="text-xs font-black uppercase tracking-widest text-slate-400 group-hover:text-indigo-600">Adicionar Estágio</span>
            </button>
            <div v-else class="bg-white dark:bg-slate-900 p-6 rounded-[32px] border border-indigo-500/30 shadow-2xl shadow-indigo-500/10 space-y-4">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-xs font-black uppercase tracking-widest text-indigo-600">Novo Estágio</h3>
                    <button @click="showAddStage = false" class="text-slate-400 hover:text-red-500"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <input v-model="newStageForm.name" type="text" placeholder="Nome do estágio..." class="w-full h-11 bg-slate-50 dark:bg-slate-800 border-none rounded-2xl px-4 text-xs font-bold focus:ring-2 focus:ring-indigo-500 shadow-inner" />
                <div class="flex items-center gap-3">
                    <input v-model="newStageForm.color" type="color" class="w-11 h-11 rounded-2xl border-none p-0 cursor-pointer overflow-hidden shadow-sm" />
                    <button @click="onAddStage" :disabled="!newStageForm.name" class="flex-1 h-11 bg-indigo-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest shadow-lg shadow-indigo-500/20 disabled:opacity-50 hover:scale-[1.02] active:scale-[0.98] transition-all">Salvar</button>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Smooth scroll and transitions for dragging */
.custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; }
</style>
