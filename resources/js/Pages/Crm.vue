<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

// Sub-components
import CrmStats from './Crm/CrmStats.vue';
import CrmPipeline from './Crm/CrmPipeline.vue';
import CrmInbox from './Crm/CrmInbox.vue';
import CrmLeadDrawer from './Crm/CrmLeadDrawer.vue';
import CrmLeadModal from './Crm/CrmLeadModal.vue';

const props = defineProps({
    pipelines: Array,
    currentPipelineId: Number,
    leads: Array,
    tags: Array,
    teamMembers: { type: [Array, Object], default: () => [] },
    teamId: Number,
    whatsappInstances: { type: Array, default: () => [] },
    conversations: { type: Array, default: () => [] },
    stats: Object,
});

const activeTab = ref('pipeline');
const localPipelines = ref([...(props.pipelines || [])]);
const selectedPipelineId = ref(props.currentPipelineId);

watch(() => props.pipelines, (newVal) => {
    localPipelines.value = [...(newVal || [])];
}, { deep: true });
const localLeads = ref([...props.leads]);
const showNewLeadModal = ref(false);
const selectedLead = ref(null);
const showLeadDrawer = ref(false);
const drawerTab = ref('activity');

const searchQuery = ref('');
const leadFilterStatus = ref('active');

// WhatsApp & Inbox State
const waInstances = ref([...(props.whatsappInstances || [])]);
const selectedInstanceId = ref('all');
const localConversations = ref([...(props.conversations || [])]);
const selectedConversation = ref(null);
const chatMessages = ref([]);
const newMessage = ref('');
const sendingMessage = ref(false);
const isLoadingHistory = ref(false);
const showInboxSidebar = ref(true);

// Tags Management
const showTagPicker = ref(false);
const localTags = ref([...(props.tags || [])]);
const newTagForm = useForm({ name: '', color: '#6366f1' });

// Message Templates
const templates = ref([]);
const showTemplates = ref(false);

const leadForm = useForm({
    name: '',
    value: 0,
    stage_id: null,
    pipeline_id: props.currentPipelineId || (props.pipelines?.[0]?.id),
    contact_id: null,
    responsible_user_id: null,
    source: 'manual',
});

// Computed
const totalUnread = computed(() => localConversations.value.reduce((sum, c) => sum + (c.unread_count || 0), 0));

const filteredLeads = computed(() => {
    let list = localLeads.value;
    if (leadFilterStatus.value !== 'all') list = list.filter(l => l.status === leadFilterStatus.value);
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(l => l.name.toLowerCase().includes(q) || l.contact?.name?.toLowerCase().includes(q));
    }
    return list;
});

const filteredConversations = computed(() => {
    let list = localConversations.value;
    if (selectedInstanceId.value !== 'all') list = list.filter(c => c.whatsapp_instance_id == selectedInstanceId.value);
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter(c => c.contact?.name?.toLowerCase().includes(q) || c.contact?.phone?.includes(q));
    }
    return list;
});

const currentPipeline = computed(() => localPipelines.value?.find(p => p.id === selectedPipelineId.value));

const stagesWithLeads = computed(() => {
    if (!currentPipeline.value?.stages) return [];
    return currentPipeline.value.stages.map(stage => ({
        ...stage,
        leads: (filteredLeads.value || []).filter(l => l.stage_id === stage.id),
    }));
});

const activityFeed = computed(() => {
    if (!selectedLead.value) return [];
    const notes = (selectedLead.value.notes || []).map(n => ({ ...n, feedType: 'note' }));
    const acts = (selectedLead.value.activities || []).map(a => ({ ...a, feedType: 'activity' }));
    return [...notes, ...acts].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});

const chatContact = computed(() => selectedConversation.value?.contact);
const chatLeads = computed(() => {
    if (!chatContact.value) return [];
    return localLeads.value.filter(l => l.contact_id === chatContact.value.id);
});
const activeChatLeads = computed(() => chatLeads.value.filter(l => l.status === 'active'));
const historicalChatLeads = computed(() => chatLeads.value.filter(l => l.status !== 'active'));

// Actions
async function refreshLeadData(id) {
    const res = await axios.get(route('crm.leads.show', id));
    selectedLead.value = res.data;
}

function moveLeadToStage({ leadId, stageId }) {
    const lead = localLeads.value.find(l => l.id === leadId);
    if (lead) lead.stage_id = stageId;
    router.put(route('crm.leads.move', leadId), { stage_id: stageId }, {
        preserveScroll: true,
        only: ['leads', 'pipelines', 'stats']
    });
}

async function openLeadDrawer(lead) {
    await refreshLeadData(lead.id);
    showLeadDrawer.value = true;
    drawerTab.value = 'activity';
}

const selectConversation = async (conv) => {
    selectedConversation.value = conv;
    chatMessages.value = [...(conv.messages_list || [])];
    const index = localConversations.value.findIndex(c => c.id === conv.id);
    if (index !== -1) localConversations.value[index].unread_count = 0;
    try { await axios.post(route('crm.inbox.read', conv.id)); } catch (e) {}
    setTimeout(() => scrollChatToBottom(), 100);
};

const handleScroll = async (e) => {
    if (e.target.scrollTop < 50 && !isLoadingHistory.value && selectedConversation.value) {
        const firstMsg = chatMessages.value?.[0];
        if (!firstMsg) return;
        isLoadingHistory.value = true;
        const oldHeight = e.target.scrollHeight;
        try {
            const res = await axios.get(route('crm.inbox.history', selectedConversation.value.id), { params: { before_id: firstMsg.id } });
            if (res.data.length > 0) {
                chatMessages.value = [...res.data, ...chatMessages.value];
                setTimeout(() => { e.target.scrollTop = e.target.scrollHeight - oldHeight; }, 0);
            }
        } catch (e) {} finally { isLoadingHistory.value = false; }
    }
};

// Flash Error Handling
const page = usePage();
watch(() => page.props.flash?.error, (err) => {
    if (err) alert(err);
}, { immediate: true });

async function sendMessage(params = {}) {
    if (sendingMessage.value) return;

    let isInternal = false;
    let formData = null;

    if (params && typeof params === 'object') {
        isInternal = params.isInternal || false;
        formData = params.formData || null;
    } else if (typeof params === 'boolean') {
        isInternal = params;
    }
    
    const content = formData ? formData.get('content') : newMessage.value;
    if (!content?.trim() && !formData) return;

    const convId = selectedConversation.value?.id;
    if (!convId) return;

    if (!formData) newMessage.value = '';
    sendingMessage.value = true;
    
    // File preview for optimistic UI
    const file = formData?.get('file');
    const mediaUrl = file ? URL.createObjectURL(file) : null;
    const type = file ? (file.type.startsWith('image/') ? 'image' : 'document') : 'text';

    const optimisticMsg = { 
        id: 'temp-'+Date.now(), 
        conversation_id: convId, 
        from_me: true, 
        type: type, 
        content, 
        media_url: mediaUrl,
        status: 'pending', 
        created_at: new Date().toISOString(),
        is_internal: isInternal,
        user: page.props.auth.user
    };
    
    chatMessages.value.push(optimisticMsg);
    nextTick(() => scrollChatToBottom());
    
    try {
        const payload = formData || { content, is_internal: isInternal };
        const headers = formData ? { 'Content-Type': 'multipart/form-data' } : {};
        const res = await axios.post(route('crm.inbox.send', convId), payload, { headers });
        const idx = chatMessages.value.findIndex(m => m.id === optimisticMsg.id);
        if (idx !== -1) chatMessages.value[idx] = res.data;
    } catch (e) { 
        optimisticMsg.status = 'failed'; 
    } finally { 
        sendingMessage.value = false; 
    }
}

async function toggleLeadTag(tag) {
    if (!selectedLead.value) return;
    let ids = selectedLead.value.tags?.map(t => t.id) || [];
    const idx = ids.indexOf(tag.id);
    if (idx === -1) ids.push(tag.id); else ids.splice(idx, 1);
    try {
        await axios.put(route('crm.leads.tags.sync', selectedLead.value.id), { tag_ids: ids });
        await refreshLeadData(selectedLead.value.id);
    } catch (e) {}
}

async function createTag() {
    if (!newTagForm.name.trim()) return;
    newTagForm.post(route('crm.tags.store'), { onSuccess: () => newTagForm.reset() });
}

async function addNote() {
    if (!newNote.value.trim() || !selectedLead.value) return;
    try {
        const res = await axios.post(route('crm.leads.notes.store', selectedLead.value.id), { content: newNote.value });
        selectedLead.value.notes.unshift(res.data);
        newNote.value = '';
    } catch (e) {}
}

const newTaskTitle = ref('');
const newNote = ref('');

async function addTask() {
    if (!newTaskTitle.value.trim() || !selectedLead.value) return;
    try {
        const res = await axios.post(route('crm.leads.tasks.store', selectedLead.value.id), { title: newTaskTitle.value });
        selectedLead.value.tasks.push(res.data);
        newTaskTitle.value = '';
    } catch (e) {}
}

async function toggleTask(task) {
    try {
        await axios.put(route('crm.tasks.complete', task.id));
        task.completed_at = new Date().toISOString();
    } catch (e) {}
}

function openNewLeadModal(data = null) {
    leadForm.reset();
    if (data && typeof data === 'object') {
        leadForm.name = data.name || '';
        leadForm.contact_id = data.contact_id || null;
        leadForm.source = 'whatsapp';
    } else if (data) {
        leadForm.stage_id = data;
        const stagePipeline = props.pipelines?.find(p => p.stages?.some(s => s.id === data));
        if (stagePipeline) leadForm.pipeline_id = stagePipeline.id;
    }
    
    if (!leadForm.pipeline_id) {
        leadForm.pipeline_id = selectedPipelineId.value || props.pipelines?.[0]?.id;
    }
    
    if (!leadForm.stage_id) {
        const pipeline = props.pipelines?.find(p => p.id === leadForm.pipeline_id);
        leadForm.stage_id = pipeline?.stages?.[0]?.id;
    }
    
    showNewLeadModal.value = true;
}

function createLead() {
    const targetPipelineId = leadForm.pipeline_id;
    leadForm.post(route('crm.leads.store'), { 
        onSuccess: () => { 
            showNewLeadModal.value = false; 
            leadForm.reset(); 
            // If lead was created for a different pipeline, switch to it
            if (targetPipelineId && selectedPipelineId.value !== targetPipelineId) {
                selectedPipelineId.value = targetPipelineId;
                router.get(route('crm'), { pipeline_id: targetPipelineId }, { only: ['leads', 'pipelines'], preserveState: true });
            }
        } 
    });
}

function deleteLead(id) {
    if (confirm('Excluir este lead?')) router.delete(route('crm.leads.destroy', id), { onSuccess: () => showLeadDrawer.value = false });
}

// Stage & Pipeline Management
function updateStage(data) {
    // Optimistic
    const pipeline = localPipelines.value.find(p => p.id === selectedPipelineId.value);
    if (pipeline) {
        const stage = pipeline.stages.find(s => s.id === data.id);
        if (stage) Object.assign(stage, data);
    }
    router.put(route('crm.stages.update', data.id), data, {
        preserveScroll: true,
        only: ['pipelines', 'leads']
    });
}

function deleteStage(id) {
    if (!confirm('Excluir este estágio?')) return;
    router.delete(route('crm.stages.destroy', id), {
        preserveScroll: true,
        only: ['pipelines', 'leads', 'flash'],
        onSuccess: () => {
             // reload will sync localPipelines via watch
        }
    });
}

function addStage(data) {
    router.post(route('crm.stages.store', data.pipeline_id), data, {
        preserveScroll: true,
        only: ['pipelines', 'leads']
    });
}

function reorderStages(stageIds) {
    // Optimistic Update
    const pipeline = localPipelines.value.find(p => p.id === selectedPipelineId.value);
    if (pipeline) {
        pipeline.stages.sort((a, b) => stageIds.indexOf(a.id) - stageIds.indexOf(b.id));
    }

    router.put(route('crm.stages.reorder', selectedPipelineId.value), { stages: stageIds }, {
        preserveScroll: true,
        only: ['pipelines']
    });
}

function deletePipeline() {
    if (!selectedPipelineId.value || !confirm('Excluir este funil inteiro?')) return;
    router.delete(route('crm.pipelines.destroy', selectedPipelineId.value), {
        onSuccess: () => {
            selectedPipelineId.value = props.pipelines.find(p => p.id !== selectedPipelineId.value)?.id || null;
        }
    });
}

const showNewPipelineModal = ref(false);
const pipelineForm = useForm({ name: '' });
function createPipeline() {
    pipelineForm.post(route('crm.pipelines.store'), { 
        onSuccess: () => { 
            showNewPipelineModal.value = false; 
            pipelineForm.reset(); 
            router.reload();
        } 
    });
}

// Websockets
let crmChannel = null, waChannel = null;
onMounted(() => {
    fetchTemplates();
    if (window.Echo && props.teamId) {
        crmChannel = window.Echo.private(`team.${props.teamId}.crm`);
        crmChannel.listen('.lead.updated', (e) => {
            const idx = localLeads.value.findIndex(l => l.id === e.lead.id);
            if (idx !== -1) { 
                localLeads.value[idx] = e.lead; 
                if (selectedLead.value?.id === e.lead.id) refreshLeadData(e.lead.id);
            } else localLeads.value.push(e.lead);
        });
        crmChannel.listen('.message.received', (e) => {
            // Deduplicate: check if ID already exists (update status)
            const existingIdx = chatMessages.value.findIndex(m => m.id === e.message.id);
            if (existingIdx !== -1) {
                chatMessages.value[existingIdx] = e.message;
                return;
            }

            // Optimistic replacement: if from me, check for pending msg with same content
            if (e.message.from_me) {
                const pendingIdx = chatMessages.value.findIndex(m => m.status === 'pending' && m.content === e.message.content);
                if (pendingIdx !== -1) {
                    chatMessages.value[pendingIdx] = e.message;
                    return;
                }
            }

            if (selectedConversation.value?.id === e.conversation_id) {
                chatMessages.value.push(e.message);
                nextTick(() => scrollChatToBottom());
            }
            const conv = localConversations.value.find(c => c.id === e.conversation_id);
            if (conv && selectedConversation.value?.id !== e.conversation_id && !e.message.from_me) conv.unread_count++;
        });
        crmChannel.listen('.conversation.updated', (e) => {
            const idx = localConversations.value.findIndex(c => c.id === e.conversation.id);
            if (idx !== -1) localConversations.value[idx] = { ...e.conversation, messages_list: localConversations.value[idx].messages_list || [] };
            else localConversations.value.unshift({ ...e.conversation, messages_list: [] });
            localConversations.value.sort((a,b) => new Date(b.last_message_at || 0) - new Date(a.last_message_at || 0));
        });
        waChannel = window.Echo.private(`team.${props.teamId}.whatsapp`);
        waChannel.listen('.status.updated', (e) => {
            const idx = waInstances.value.findIndex(i => i.id === e.instance_id);
            if (idx !== -1) waInstances.value[idx] = { ...waInstances.value[idx], status: e.status, phone: e.phone };
        });
    }
});

onUnmounted(() => {
    if (crmChannel) window.Echo?.leave(`team.${props.teamId}.crm`);
    if (waChannel) window.Echo?.leave(`team.${props.teamId}.whatsapp`);
});

watch(() => props.leads, (val) => { localLeads.value = [...val]; });
watch(() => props.conversations, (val) => { localConversations.value = [...val]; });
watch(() => props.tags, (val) => { localTags.value = [...val]; });

const fetchTemplates = async () => { try { const res = await axios.get(route('crm.inbox.templates')); templates.value = res.data; } catch (e) {} };
const scrollChatToBottom = () => { const el = document.getElementById('chat-messages'); if (el) el.scrollTop = el.scrollHeight; };
</script>

<template>
    <AppLayout title="CRM Pro">
        <template #header>
            <div class="flex items-center justify-between gap-4 w-full">
                <h2 class="font-bold text-2xl text-slate-800 dark:text-white leading-tight">CRM</h2>
                
                <div class="flex-1 max-w-lg relative group">
                    <input v-model="searchQuery" type="text" 
                        placeholder="Pesquisar leads, contatos ou empresas..." 
                        class="w-full h-11 bg-gray-100 dark:bg-slate-800/80 border-none rounded-2xl pl-11 pr-4 text-sm focus:ring-2 focus:ring-indigo-500 transition-all dark:placeholder-slate-500 dark:text-white" />
                    <svg class="absolute left-4 top-3.5 w-4 h-4 text-slate-400 group-focus-within:text-indigo-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>

                <div class="flex items-center gap-2">
                    <button @click="openNewLeadModal()" class="h-11 px-5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-2xl shadow-lg shadow-indigo-500/20 transition-all flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Novo Lead
                    </button>
                </div>
            </div>
        </template>

        <div class="h-[calc(100vh-73px)] flex flex-col overflow-hidden bg-white dark:bg-slate-950">
            <CrmStats :stats="props.stats" :wa-instances="waInstances" />


            <!-- TABS & VIEW CONTROLS -->
            <div class="px-6 border-b border-gray-200 dark:border-slate-800 flex items-center justify-between">
                <nav class="flex gap-1 -mb-px">
                    <button v-for="tab in [{id:'pipeline', label:'Pipeline', icon:'📊'},{id:'inbox', label:'Inbox', icon:'💬', badge: totalUnread},{id:'contacts', label:'Contatos', icon:'👤'}]"
                        :key="tab.id" @click="activeTab = tab.id"
                        :class="activeTab === tab.id ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 bg-indigo-50/50 dark:bg-indigo-500/5' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        class="px-6 py-4 text-sm font-bold border-b-2 transition-all flex items-center gap-2.5">
                        <span class="text-lg opacity-80">{{ tab.icon }}</span> 
                        {{ tab.label }}
                        <span v-if="tab.badge > 0" class="px-1.5 py-0.5 bg-red-500 text-white text-[9px] font-black rounded-full">{{ tab.badge }}</span>
                    </button>
                </nav>

                <!-- Sub-filters & Pipeline Switcher -->
                <div class="flex items-center gap-3">
                    <template v-if="activeTab === 'pipeline'">
                        <!-- Pipeline Switcher -->
                        <div class="flex items-center gap-2 p-1.5 bg-slate-100 dark:bg-slate-800 rounded-xl mr-4 border border-gray-200 dark:border-slate-700">
                            <select v-model="selectedPipelineId" @change="router.get(route('crm'), { pipeline_id: selectedPipelineId }, { only: ['leads', 'pipelines'], preserveState: true })" class="text-[10px] font-black uppercase bg-transparent border-none focus:ring-0 text-slate-600 dark:text-slate-300 cursor-pointer">
                                <option v-for="p in props.pipelines" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <div class="flex gap-1 border-l border-gray-200 dark:border-slate-700 pl-2 pr-1">
                                <button @click="showNewPipelineModal = true" class="p-1 text-slate-400 hover:text-indigo-500 transition-all"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 4v16m8-8H4"/></svg></button>
                                <button v-if="!currentPipeline?.is_default" @click="deletePipeline" class="p-1 text-slate-400 hover:text-red-500 transition-all"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                            </div>
                        </div>

                        <select v-model="leadFilterStatus" class="text-[10px] font-black uppercase bg-transparent border-none focus:ring-0 text-slate-400 cursor-pointer hover:text-indigo-500">
                            <option value="active">Leads Ativos</option>
                            <option value="won">Ganhos</option>
                            <option value="lost">Perdidos</option>
                            <option value="all">Todos</option>
                        </select>
                    </template>
                    <template v-if="activeTab === 'inbox'">
                        <select v-model="selectedInstanceId" class="text-[10px] font-black uppercase bg-transparent border-none focus:ring-0 text-slate-400 cursor-pointer hover:text-indigo-500">
                            <option value="all">Todas Instâncias</option>
                            <option v-for="inst in waInstances" :key="inst.id" :value="inst.id">{{ inst.name }}</option>
                        </select>
                    </template>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <div class="flex-1 overflow-hidden relative">
                <!-- Pipeline View -->
                <CrmPipeline v-if="activeTab === 'pipeline'" 
                    :stages-with-leads="stagesWithLeads" 
                    :pipeline-id="selectedPipelineId"
                    @open-lead="openLeadDrawer" 
                    @move-lead="moveLeadToStage"
                    @update-stage="updateStage"
                    @delete-stage="deleteStage"
                    @add-stage="addStage"
                    @reorder-stages="reorderStages" />

                <!-- Inbox View -->
                <CrmInbox v-if="activeTab === 'inbox'"
                    :conversations="filteredConversations"
                    :selected-conversation="selectedConversation"
                    :chat-messages="chatMessages"
                    :templates="templates"
                    :show-templates="showTemplates"
                    :sending-message="sendingMessage"
                    v-model:new-message="newMessage"
                    :is-loading-history="isLoadingHistory"
                    :show-inbox-sidebar="showInboxSidebar"
                    :chat-contact="chatContact"
                    :active-chat-leads="activeChatLeads"
                    :historical-chat-leads="historicalChatLeads"
                    :local-leads="localLeads"
                    :wa-instances="waInstances"
                    @select-conversation="selectConversation"
                    @send-message="sendMessage"
                    @load-history="handleScroll"
                    @toggle-sidebar="showInboxSidebar = !showInboxSidebar"
                    @insert-template="newMessage = $event; showTemplates = false"
                    @toggle-templates="showTemplates = !showTemplates"
                    @open-lead="openLeadDrawer"
                    @create-lead="openNewLeadModal" />

                <!-- Contacts View (Redesign) -->
                <div v-else-if="activeTab === 'contacts'" class="h-full overflow-y-auto p-12 bg-slate-50/20 dark:bg-transparent custom-scrollbar">
                    <div class="max-w-5xl mx-auto space-y-6">
                        <div v-for="conv in localConversations" :key="conv.id" 
                            class="flex items-center gap-6 p-6 bg-white dark:bg-slate-900 rounded-[32px] border border-gray-100 dark:border-slate-800 shadow-sm hover:shadow-xl transition-all group">
                            <div class="w-16 h-16 rounded-3xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-black text-2xl shadow-lg shadow-indigo-500/20">
                                {{ conv.contact?.name?.[0] || '?' }}
                            </div>
                            <div class="flex-1">
                                <h4 class="text-xl font-black text-slate-800 dark:text-white mb-1 group-hover:text-indigo-600 transition-colors">{{ conv.contact?.name }}</h4>
                                <div class="flex items-center gap-4 text-sm font-bold text-slate-400">
                                    <span class="flex items-center gap-1.5"><svg class="w-4 h-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>{{ conv.contact?.phone }}</span>
                                    <span v-if="conv.contact?.company" class="flex items-center gap-1.5 pl-4 border-l border-gray-100 dark:border-slate-800"><svg class="w-4 h-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>{{ conv.contact?.company }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button @click="selectConversation(conv); activeTab = 'inbox'" class="h-12 px-6 bg-slate-100 dark:bg-slate-800 hover:bg-indigo-600 hover:text-white text-slate-700 dark:text-slate-300 font-black rounded-2xl transition-all flex items-center gap-2">
                                    Chat
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- NEW PIPELINE MODAL -->
        <Teleport to="body">
            <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                <div v-if="showNewPipelineModal" class="fixed inset-0 z-[100] flex items-center justify-center p-6">
                    <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-md" @click="showNewPipelineModal = false"></div>
                    <div class="relative bg-white dark:bg-slate-900 rounded-[40px] shadow-2xl p-10 w-full max-w-md border border-gray-100 dark:border-slate-800 overflow-hidden">
                        <h3 class="text-2xl font-black text-slate-800 dark:text-white mb-6">Novo Funil de Vendas</h3>
                        <form @submit.prevent="createPipeline" class="space-y-6">
                            <div>
                                <label class="block text-[10px] font-black uppercase text-slate-400 mb-2">Nome do Funil</label>
                                <input v-model="pipelineForm.name" type="text" required class="w-full h-14 bg-slate-50 dark:bg-slate-800 border-none rounded-2xl px-6 font-bold focus:ring-2 focus:ring-indigo-500" placeholder="Ex: Vendas High Ticket" />
                            </div>
                            <div class="flex gap-4">
                                <button type="button" @click="showNewPipelineModal = false" class="flex-1 h-16 bg-slate-50 dark:bg-slate-800 text-slate-600 font-black rounded-3xl hover:bg-slate-100 transition-all">Cancelar</button>
                                <button type="submit" :disabled="pipelineForm.processing" class="flex-1 h-16 bg-indigo-600 text-white font-black rounded-3xl shadow-xl shadow-indigo-500/30 hover:shadow-indigo-500/50 transition-all">Criar Funil</button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>
        </Teleport>

        <CrmLeadDrawer :show="showLeadDrawer" 
            :lead="selectedLead" 
            v-model:tab="drawerTab" 
            :activity-feed="activityFeed" 
            :tags="props.tags" 
            :local-tags="localTags" 
            :show-tag-picker="showTagPicker" 
            :new-tag-form="newTagForm" 
            v-model:new-note="newNote" 
            v-model:new-task-title="newTaskTitle" 
            @close="showLeadDrawer = false" 
            @delete="deleteLead" 
            @add-note="addNote" 
            @add-task="addTask" 
            @toggle-task="toggleTask" 
            @toggle-tag="toggleLeadTag" 
            @create-tag="createTag" 
            @toggle-tag-picker="showTagPicker = !showTagPicker" />

        <CrmLeadModal :show="showNewLeadModal" 
            :lead-form="leadForm" 
            :pipelines="props.pipelines"
            @close="showNewLeadModal = false" 
            @submit="createLead" />
    </AppLayout>
</template>

<style>
.custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #1e293b; }
.scroller-hidden::-webkit-scrollbar { display: none; }
</style>
