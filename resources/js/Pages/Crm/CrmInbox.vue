<script setup>
import { ref, computed, nextTick } from 'vue';
import axios from 'axios';

const props = defineProps({
    conversations: Array,
    selectedConversation: Object,
    chatMessages: Array,
    templates: Array,
    showTemplates: Boolean,
    sendingMessage: Boolean,
    newMessage: String,
    isLoadingHistory: Boolean,
    showInboxSidebar: Boolean,
    chatContact: Object,
    activeChatLeads: Array,
    historicalChatLeads: Array,
    localLeads: Array,
    waInstances: Array,
});

const isInternalMode = ref(false);
const aiSummary = ref(null);

const currentInstance = computed(() => {
    if (!props.selectedConversation || !props.waInstances) return null;
    return props.waInstances.find(i => i.id === props.selectedConversation.whatsapp_instance_id);
});
const isSummarizing = ref(false);
const attachedFile = ref(null);
const fileInput = ref(null);

const emit = defineEmits([
    'select-conversation', 
    'send-message', 
    'update:newMessage', 
    'load-history', 
    'toggle-sidebar', 
    'insert-template', 
    'toggle-templates',
    'open-lead',
    'create-lead',
    'summarize',
    'add-message'
]);

const showPaymentModal = ref(false);
const isGeneratingPayment = ref(false);
const paymentForm = ref({
    amount: '',
    cost: '',
    description: '',
    lead_id: null
});

const openPaymentModal = (leadId = null) => {
    paymentForm.value = {
        amount: '',
        cost: '',
        description: '',
        lead_id: leadId
    };
    showPaymentModal.value = true;
};

const submitPayment = async () => {
    if (!paymentForm.value.amount || !paymentForm.value.description) return;
    
    isGeneratingPayment.value = true;
    try {
        const response = await axios.post(route('crm.payments.store'), {
            contact_id: props.chatContact.id,
            lead_id: paymentForm.value.lead_id,
            amount: paymentForm.value.amount,
            cost: paymentForm.value.cost,
            description: paymentForm.value.description
        });
        
        const { message } = response.data;
        
        // Auto-insert link into message input or emit event to send
        emit('update:newMessage', message);
        
        showPaymentModal.value = false;
        alert('Link de pagamento gerado e inserido no chat!');
    } catch (e) {
        alert(e.response?.data?.error || 'Erro ao gerar pagamento.');
    } finally {
        isGeneratingPayment.value = false;
    }
};

async function handleSummarize() {
    isSummarizing.value = true;
    try {
        const res = await axios.get(route('crm.inbox.summarize', props.selectedConversation.id));
        aiSummary.value = res.data.summary;
    } catch (e) {
        aiSummary.value = 'Erro ao gerar resumo.';
    } finally {
        isSummarizing.value = false;
    }
}

const MAX_WIDTH = 1600;
const MAX_HEIGHT = 1600;
const QUALITY = 0.8;

const onFileChange = async (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        if (file.size > 10 * 1024 * 1024) return alert('Arquivo muito grande (máximo 10MB)');
        attachedFile.value = file;
        return;
    }

    // Client-side Image Compression
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (event) => {
        const img = new Image();
        img.src = event.target.result;
        img.onload = () => {
            let width = img.width;
            let height = img.height;

            if (width > height) {
                if (width > MAX_WIDTH) {
                    height = Math.round((height * MAX_WIDTH) / width);
                    width = MAX_WIDTH;
                }
            } else {
                if (height > MAX_HEIGHT) {
                    width = Math.round((width * MAX_HEIGHT) / height);
                    height = MAX_HEIGHT;
                }
            }

            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            canvas.toBlob((blob) => {
                const compressedFile = new File([blob], file.name.replace(/\.[^/.]+$/, "") + ".webp", {
                    type: 'image/webp',
                    lastModified: Date.now()
                });
                
                if (compressedFile.size > 10 * 1024 * 1024) return alert('Imagem muito grande (máximo 10MB) mesmo após compressão.');
                attachedFile.value = compressedFile;
            }, 'image/webp', QUALITY);
        };
    };
};

const onSendMessage = () => {
    if ((!props.newMessage?.trim() && !attachedFile.value) || props.sendingMessage) return;
    
    if (attachedFile.value) {
        const formData = new FormData();
        formData.append('content', props.newMessage || '');
        formData.append('file', attachedFile.value);
        formData.append('is_internal', isInternalMode.value);
        
        emit('send-message', { isInternal: isInternalMode.value, formData });
        attachedFile.value = null;
        if (fileInput.value) fileInput.value.value = '';
    } else {
        emit('send-message', { isInternal: isInternalMode.value });
    }
};
// Keep internal mode active if it was on, or reset? Let's reset for safety but keep it selectable


const formatValue = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);

function timeAgo(date) {
    if (!date) return '';
    const diff = Math.floor((new Date() - new Date(date)) / 1000);
    if (diff < 60) return 'agora';
    if (diff < 3600) return `${Math.floor(diff/60)}m`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h`;
    return `${Math.floor(diff/86400)}d`;
}

function statusIcon(status) { 
    const icons = { pending: '🕐', sent: '✓', delivered: '✓✓', read: '✓✓', failed: '✗' }; 
    return icons[status] || ''; 
}

const handleScroll = (e) => emit('load-history', e);
</script>

<template>
    <div class="h-full flex overflow-hidden">
        <!-- Sidebar and Chat Body... -->
        <div class="w-80 shrink-0 border-r border-gray-100 dark:border-slate-800 flex flex-col bg-white dark:bg-slate-900/50">
            <div class="p-4 bg-slate-50/50 dark:bg-transparent border-b border-gray-100 dark:border-slate-800">
                <h3 class="font-black text-slate-800 dark:text-white uppercase tracking-widest text-xs mb-1">Canais Ativos</h3>
                <p class="text-[10px] font-bold text-slate-400">{{ conversations.length }} conversas encontradas</p>
            </div>
            <div class="flex-1 overflow-y-auto custom-scrollbar">
                <div v-for="conv in conversations" :key="conv.id" @click="emit('select-conversation', conv)"
                    :class="selectedConversation?.id === conv.id ? 'bg-indigo-50/80 dark:bg-indigo-600/10 border-indigo-500' : 'hover:bg-slate-50 dark:hover:bg-slate-800 border-transparent'"
                    class="p-4 cursor-pointer transition-all border-l-4">
                    <div class="flex items-center gap-3">
                        <div :class="conv.contact?.is_group ? 'bg-indigo-600 text-white' : 'bg-indigo-500/10 text-indigo-600'" class="w-12 h-12 rounded-2xl flex items-center justify-center font-black text-sm relative">
                            {{ conv.contact?.name?.[0] || '?' }}
                            <div v-if="conv.unread_count > 0" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[9px] rounded-full flex items-center justify-center animate-bounce shadow-lg border-2 border-white dark:border-slate-900">{{ conv.unread_count }}</div>
                            <div v-if="conv.contact?.is_group" class="absolute -bottom-1 -right-1 w-4 h-4 bg-indigo-600 text-white text-[7px] rounded-lg flex items-center justify-center shadow-lg border-2 border-white dark:border-slate-900">G</div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-baseline mb-0.5">
                                <div class="flex items-center gap-1 min-w-0">
                                    <span class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ conv.contact?.name || conv.contact?.phone || '...' }}</span>
                                    <span v-if="conv.contact?.is_group" class="px-1.5 py-0.5 bg-indigo-500/10 text-indigo-600 rounded text-[7px] font-black uppercase">Grupo</span>
                                </div>
                                <span class="text-[9px] font-black text-slate-400 ml-2">{{ timeAgo(conv.last_message_at) }}</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 truncate opacity-70">
                                {{ conv.messages_list?.[conv.messages_list.length - 1]?.content || conv.contact?.phone }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 flex flex-col bg-slate-50 dark:bg-slate-950/50 relative">
            <template v-if="selectedConversation">
                <div class="h-20 px-6 bg-white dark:bg-slate-900 border-b border-gray-100 dark:border-slate-800 flex items-center gap-4 z-10 shadow-sm shrink-0">
                    <div class="w-10 h-10 rounded-xl bg-green-500 flex items-center justify-center text-white shrink-0">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <h4 class="text-sm font-black text-slate-800 dark:text-white truncate">{{ selectedConversation.contact?.name }}</h4>
                            <span v-if="selectedConversation.contact?.is_group" class="px-1.5 py-0.5 bg-indigo-500/10 text-indigo-500 rounded text-[8px] font-black uppercase">Grupo</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ selectedConversation.contact?.phone }}</p>
                            <span v-if="chatLead" class="px-2 py-0.5 bg-indigo-500/10 text-indigo-500 rounded-md text-[9px] font-black uppercase">Lead Ativo</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- AI Summarize Button -->
                        <button @click="handleSummarize" 
                            :disabled="isSummarizing"
                            class="flex items-center gap-2 px-4 py-2 bg-indigo-500 hover:bg-indigo-600 text-white rounded-xl text-[10px] font-black uppercase transition-all shadow-lg shadow-indigo-500/20 disabled:opacity-50">
                            <svg v-if="!isSummarizing" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>
                            <div v-else class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></div>
                            {{ isSummarizing ? 'Resumindo...' : 'Resumir IA' }}
                        </button>
                        
                        <button @click="emit('toggle-sidebar')" 
                            :class="showInboxSidebar ? 'text-indigo-600 bg-indigo-50/50' : 'text-slate-400 hover:text-indigo-600 hover:bg-slate-50'"
                            class="p-2.5 rounded-xl transition-all border border-transparent hover:border-indigo-100">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 12.75a.75.75 0 110-1.5.75.75 0 010 1.5zM12 18.75a.75.75 0 110-1.5.75.75 0 010 1.5z"/></svg>
                        </button>
                    </div>
                </div>

                <div class="flex-1 flex overflow-hidden">
                    <div class="flex-1 flex flex-col min-w-0 pointer-events-auto">
                        <!-- Status Warning Banner -->
                        <div v-if="currentInstance && currentInstance.status !== 'connected'" class="mx-8 mt-4 p-3 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-900/50 rounded-2xl flex items-center justify-between shadow-sm">
                            <div class="flex items-center gap-3">
                                <span class="relative flex h-2 w-2">
                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                  <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                </span>
                                <p class="text-xs font-bold text-red-600 dark:text-red-400 flex items-center gap-2">
                                    {{ currentInstance.status === 'connecting' ? 'Reconectando conexão WhatsApp...' : 'Conexão WhatsApp offline.' }}
                                </p>
                            </div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-red-500 opacity-60">Mensagens podem falhar</span>
                        </div>

                        <!-- AI Summary Box -->
                        <div v-if="aiSummary" class="mx-8 mt-4 p-4 bg-indigo-600 dark:bg-indigo-500 rounded-3xl text-white shadow-xl relative animate-in slide-in-from-top duration-300">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="p-1 bg-white/20 rounded-lg"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></span>
                                    <span class="text-[10px] font-black uppercase tracking-widest">Resumo da IA</span>
                                </div>
                                <button @click="aiSummary = null" class="opacity-60 hover:opacity-100"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </div>
                            <p class="text-xs font-medium leading-relaxed">{{ aiSummary }}</p>
                        </div>

                        <div id="chat-messages" @scroll="handleScroll" class="flex-1 overflow-y-auto p-8 space-y-4 custom-scrollbar bg-slate-50/50 dark:bg-transparent">
                            <div v-if="isLoadingHistory" class="flex justify-center py-4">
                                <div class="w-6 h-6 border-2 border-indigo-500/20 border-t-indigo-500 rounded-full animate-spin"></div>
                            </div>

                            <div v-for="(msg, i) in chatMessages" :key="msg.id"
                                :class="msg.from_me ? 'ml-auto items-end text-right' : 'mr-auto items-start text-left'"
                                class="flex flex-col max-w-[85%] group">
                                
                                <div v-if="msg.is_internal" class="mb-1 flex items-center gap-1.5 text-[9px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-widest px-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    Nota Interna • {{ msg.user?.name }}
                                </div>

                                <div v-if="msg.media_url" class="mb-2 rounded-2xl overflow-hidden border border-gray-100 dark:border-slate-800 shadow-lg bg-white dark:bg-slate-800 p-1">
                                    <img v-if="msg.type === 'image'" :src="msg.media_url" class="max-w-[300px] max-h-[400px] object-cover rounded-xl cursor-pointer hover:opacity-90 transition-opacity" @click="window.open(msg.media_url)" />
                                    <audio v-else-if="msg.type === 'audio'" :src="msg.media_url" controls class="max-w-[240px] h-10" />
                                    <video v-else-if="msg.type === 'video'" :src="msg.media_url" controls class="max-w-[300px] rounded-xl" />
                                    <div v-else class="p-4 flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-slate-500">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                        </div>
                                        <a :href="msg.media_url" target="_blank" class="text-xs font-bold text-indigo-600 hover:underline">Download file</a>
                                    </div>
                                </div>

                                <div v-if="msg.content" 
                                    :class="[
                                        msg.is_internal ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 border-amber-200 dark:border-amber-800/50' : 
                                        msg.from_me ? 'bg-indigo-600 text-white shadow-indigo-500/10' : 'bg-white dark:bg-slate-800 text-slate-800 dark:text-white border-gray-100 dark:border-slate-700 shadow-sm',
                                        msg.from_me ? 'rounded-t-3xl rounded-bl-3xl' : 'rounded-t-3xl rounded-br-3xl'
                                    ]"
                                    class="px-5 py-3.5 text-sm leading-relaxed font-medium border shadow-xl">
                                    <p class="whitespace-pre-wrap">{{ msg.content }}</p>
                                </div>

                                <div class="flex items-center gap-2 mt-1.5 px-1 opacity-50">
                                    <span class="text-[9px] font-black uppercase tracking-widest">{{ timeAgo(msg.created_at || msg.message_timestamp) }}</span>
                                    <span v-if="msg.from_me && !msg.is_internal" class="text-[10px]">{{ statusIcon(msg.status) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 bg-white dark:bg-slate-900 border-t border-gray-100 dark:border-slate-800 shrink-0">
                            <!-- Internal Mode Toggle -->
                            <div class="flex items-center gap-4 mb-4">
                                <button type="button" @click="isInternalMode = false" 
                                    :class="!isInternalMode ? 'text-indigo-600 bg-indigo-50 border-indigo-200' : 'text-slate-400 border-transparent'"
                                    class="flex-1 py-2 px-4 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all">WhatsApp</button>
                                <button type="button" @click="isInternalMode = true" 
                                    :class="isInternalMode ? 'text-amber-600 bg-amber-50 border-amber-200' : 'text-slate-400 border-transparent'"
                                    class="flex-1 py-2 px-4 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all flex items-center justify-center gap-2">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    Nota Interna
                                </button>
                            </div>

                            <form @submit.prevent="onSendMessage" class="flex gap-4 items-center">
                                <!-- Attachment Preview Chip -->
                                <div v-if="attachedFile" class="mb-3 flex items-center gap-2 p-2 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl border border-indigo-100 dark:border-indigo-500/20 w-fit animate-in fade-in slide-in-from-bottom-2">
                                    <div class="w-8 h-8 rounded-lg bg-indigo-500 text-white flex items-center justify-center">
                                        <svg v-if="attachedFile.type.startsWith('image/')" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[10px] font-black text-slate-800 dark:text-white truncate max-w-[150px]">{{ attachedFile.name }}</p>
                                        <p class="text-[9px] text-slate-400 uppercase font-black">{{ (attachedFile.size / 1024 / 1024).toFixed(2) }} MB</p>
                                    </div>
                                    <button @click="attachedFile = null" class="p-1 hover:bg-white dark:hover:bg-slate-800 rounded-lg text-slate-400 hover:text-red-500 transition-all">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>

                                <div class="flex-1 relative">
                                    <input :value="newMessage" @input="emit('update:newMessage', $event.target.value)" type="text" 
                                        :placeholder="isInternalMode ? 'Escrever nota apenas para a equipe...' : 'Escrever resposta de WhatsApp...'"
                                        class="w-full h-14 bg-slate-50 dark:bg-slate-800/80 border-none rounded-2xl pl-16 pr-6 text-sm focus:ring-2 focus:ring-indigo-500 dark:text-white transition-all shadow-inner" />
                                    
                                    <!-- Attachment Button -->
                                    <div class="absolute left-4 top-4">
                                        <button type="button" @click="fileInput.click()" class="text-slate-400 hover:text-indigo-500 transition-colors p-1" title="Anexar Arquivo">
                                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94a3 3 0 114.243 4.243l-9.172 9.172a1.5 1.5 0 01-2.121-2.121l8.828-8.828M15 9.75l-4.5 4.5" /></svg>
                                        </button>
                                        <input type="file" ref="fileInput" class="hidden" @change="onFileChange" />
                                    </div>
                                    
                                    <div class="absolute right-4 top-4 flex gap-2">
                                        <div class="relative">
                                            <button type="button" @click="emit('toggle-templates')" class="text-slate-400 hover:text-indigo-500 transition-colors p-1" title="Templates">
                                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                            </button>
                                            <!-- ... (Templates Dropdown) ... -->
                                            
                                            <div v-if="showTemplates" class="absolute bottom-full right-0 mb-2 w-64 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-slate-700 overflow-hidden z-[110]">
                                                <div class="p-4 border-b border-gray-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50">
                                                    <span class="text-[10px] font-black uppercase text-slate-400">Respostas Rápidas</span>
                                                </div>
                                                <div class="max-h-60 overflow-y-auto custom-scrollbar">
                                                    <button v-for="t in templates" :key="t.id" @click="emit('insert-template', t.content)" type="button"
                                                        class="w-full p-4 text-left hover:bg-indigo-50 dark:hover:bg-indigo-500/10 transition-colors border-b border-gray-50 dark:border-slate-700/50 last:border-0">
                                                        <p class="text-xs font-bold text-slate-800 dark:text-white mb-1">{{ t.title }}</p>
                                                        <p class="text-[10px] text-slate-400 truncate">{{ t.content }}</p>
                                                    </button>
                                                    <div v-if="templates.length === 0" class="p-8 text-center text-[10px] font-bold text-slate-400">Nenhum template cadastrado</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" :disabled="sendingMessage || (!newMessage?.trim() && !attachedFile)"
                                    class="w-14 h-14 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-500/30 transition-all disabled:opacity-50 disabled:grayscale">
                                    <svg v-if="!sendingMessage" class="w-6 h-6 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                    <div v-else class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                                </button>
                            </form>
                        </div>
                    </div>

                    <transition enter-active-class="transition-all duration-300 ease-in-out" enter-from-class="w-0 opacity-0" enter-to-class="w-80 opacity-100" leave-active-class="transition-all duration-300 ease-in-out" leave-from-class="w-80 opacity-100" leave-to-class="w-0 opacity-0">
                        <div v-if="showInboxSidebar" class="w-80 border-l border-gray-100 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 flex flex-col gap-6 overflow-y-auto custom-scrollbar shrink-0">
                            <div>
                                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Contato</h3>
                                <div class="flex items-center gap-3 p-4 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-gray-100 dark:border-slate-800">
                                <div :class="chatContact?.is_group ? 'bg-indigo-600 text-white' : 'bg-indigo-500 text-white'" class="w-10 h-10 rounded-xl flex items-center justify-center font-black">{{ chatContact?.name?.[0] }}</div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ chatContact?.name }}</p>
                                        <span v-if="chatContact?.is_group" class="px-1.5 py-0.5 bg-indigo-500/10 text-indigo-600 rounded text-[7px] font-black uppercase">Grupo</span>
                                    </div>
                                    <p class="text-[10px] text-slate-400">{{ chatContact?.phone }}</p>
                                </div>
                                </div>
                                <div v-if="chatContact?.company" class="mt-4 flex items-center gap-2 px-1">
                                    <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <span class="text-xs font-bold text-slate-600 dark:text-slate-300">{{ chatContact.company }}</span>
                                </div>
                            </div>

                            <!-- Multi-Deal Management (Recurring Leads) -->
                            <div class="space-y-6">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest">Oportunidades</h3>
                                    <button @click="emit('create-lead', { name: chatContact?.name, contact_id: chatContact?.id })" 
                                        class="p-1.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 rounded-lg hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
                                    </button>
                                </div>

                                <!-- Active Leads -->
                                <div v-if="activeChatLeads.length > 0" class="space-y-3">
                                    <div v-for="lead in activeChatLeads" :key="lead.id" 
                                        @click="emit('open-lead', lead)" 
                                        class="p-4 bg-indigo-50/50 dark:bg-indigo-500/5 rounded-2xl border border-indigo-100 dark:border-indigo-500/20 cursor-pointer hover:border-indigo-500 transition-all group">
                                        <div class="flex justify-between items-start mb-2">
                                            <h4 class="text-sm font-black text-slate-800 dark:text-white underline decoration-indigo-200">{{ lead.name }}</h4>
                                            <span class="text-[10px] font-black text-indigo-600">{{ formatValue(lead.value) }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-2 h-2 rounded-full" :style="{ backgroundColor: lead.stage?.color }"></div>
                                            <span class="text-[10px] font-black text-slate-500 uppercase">{{ lead.stage?.name }}</span>
                                        </div>
                                        <div class="mt-3 pt-3 border-t border-indigo-100 dark:border-indigo-500/10 flex justify-end">
                                            <button @click.stop="openPaymentModal(lead.id)" class="px-3 py-1.5 bg-green-500/10 text-green-600 rounded-lg text-[9px] font-black uppercase hover:bg-green-600 hover:text-white transition-all flex items-center gap-1.5">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                Cobrar Lead
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Finished Leads (History) -->
                                <div v-if="historicalChatLeads.length > 0">
                                    <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-1">Histórico</h4>
                                    <div class="space-y-2">
                                        <div v-for="lead in historicalChatLeads" :key="lead.id" 
                                            @click="emit('open-lead', lead)"
                                            class="p-3 bg-slate-50 dark:bg-slate-800/30 rounded-xl border border-gray-100 dark:border-slate-800 cursor-pointer hover:bg-white dark:hover:bg-slate-800 transition-all flex items-center justify-between opacity-70 hover:opacity-100">
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold text-slate-700 dark:text-slate-300 truncate">{{ lead.name }}</p>
                                                <p class="text-[9px] font-black uppercase" :class="lead.status === 'won' ? 'text-green-500' : 'text-red-400'">{{ lead.status === 'won' ? 'Ganho' : 'Perdido' }}</p>
                                            </div>
                                            <span class="text-[9px] font-black text-slate-400">{{ formatValue(lead.value) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Financeiro -->
                                <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-slate-800">
                                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest px-1">Financeiro</h3>
                                    <button @click="openPaymentModal()" 
                                        class="w-full p-4 bg-green-50 dark:bg-green-500/10 text-green-600 rounded-2xl border border-green-100 dark:border-green-500/20 hover:bg-green-600 hover:text-white transition-all flex items-center justify-center gap-2 group">
                                        <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span class="text-xs font-black uppercase">Emitir Cobrança</span>
                                    </button>
                                </div>

                                <!-- Empty State -->
                                <div v-if="activeChatLeads.length === 0 && historicalChatLeads.length === 0" 
                                    class="flex flex-col items-center justify-center p-8 bg-slate-50 dark:bg-slate-800/30 rounded-3xl border border-dashed border-gray-200 dark:border-slate-800">
                                    <svg class="w-12 h-12 text-slate-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <p class="text-[10px] font-black text-slate-400 uppercase mb-4 text-center">Nenhum lead encontrado para este contato</p>
                                    <button @click="emit('create-lead', { name: chatContact?.name, contact_id: chatContact?.id })" class="px-4 py-2 bg-white dark:bg-slate-800 text-indigo-600 text-[10px] font-black uppercase rounded-xl border border-indigo-100 dark:border-slate-700 hover:bg-indigo-600 hover:text-white transition-all shadow-sm">Criar Primeiro Lead</button>
                                </div>
                            </div>
                            
                            <!-- Media Explorer Section -->
                            <div v-if="chatMessages.some(m => m.media_url)">
                                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Galeria de Mídia</h3>
                                <div class="grid grid-cols-3 gap-2">
                                    <template v-for="m in chatMessages.filter(msg => msg.media_url).slice(-9)" :key="m.id">
                                        <div v-if="m.type === 'image'" class="aspect-square bg-slate-100 dark:bg-slate-800 rounded-xl overflow-hidden cursor-pointer hover:ring-2 ring-indigo-500 transition-all border border-gray-100 dark:border-slate-800" @click="window.open(m.media_url)">
                                            <img :src="m.media_url" class="w-full h-full object-cover" />
                                        </div>
                                        <div v-else class="aspect-square bg-slate-50 dark:bg-slate-800 rounded-xl flex items-center justify-center text-slate-400 group cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900 shadow-sm border border-gray-100 dark:border-slate-800" @click="window.open(m.media_url)">
                                            <svg v-if="m.type === 'video'" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                            <svg v-else class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="mt-auto">
                                <div class="p-4 bg-amber-50 dark:bg-amber-500/5 rounded-2xl border border-amber-100 dark:border-amber-500/20">
                                    <div class="flex gap-2">
                                        <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <p class="text-[10px] font-bold text-amber-700 dark:text-amber-400">Atendimento em tempo real via WhatsApp. Lembre-se de ser cordial.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </transition>
                </div>
            </template>
        </div>
        <!-- Payment Modal -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6 bg-slate-900/60 backdrop-blur-sm transition-all animate-in fade-in duration-300">
            <div @click.stop class="w-full max-w-md bg-white dark:bg-slate-900 rounded-[2.5rem] shadow-2xl overflow-hidden border border-gray-100 dark:border-slate-800 transition-all animate-in zoom-in-95 duration-300">
                <div class="px-8 pt-8 pb-6 bg-slate-50/50 dark:bg-slate-800/50 border-b border-gray-100 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-2">
                        <div class="p-3 bg-green-500/10 rounded-2xl">
                            <svg class="w-6 h-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <button @click="showPaymentModal = false" class="text-slate-400 hover:text-red-500 transition-colors">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <h3 class="text-lg font-black text-slate-800 dark:text-white uppercase tracking-wider">Nova Cobrança</h3>
                    <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-widest">Gerar link de pagamento via Mercado Pago</p>
                </div>

                <div class="p-8 space-y-6">
                    <div>
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">Descrição do Serviço</label>
                        <input v-model="paymentForm.description" type="text" placeholder="Ex: Consultoria, Produto A, etc"
                            class="w-full bg-slate-50 dark:bg-slate-800/50 border-0 rounded-2xl px-4 py-4 text-sm font-bold text-slate-800 dark:text-white focus:ring-2 focus:ring-green-500 transition-all placeholder:text-slate-400">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">Valor do Lead</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400">R$</span>
                                <input v-model="paymentForm.amount" type="number" step="0.01" placeholder="0,00"
                                    class="w-full bg-slate-50 dark:bg-slate-800/50 border-0 rounded-2xl pl-10 pr-4 py-4 text-sm font-black text-slate-800 dark:text-white focus:ring-2 focus:ring-green-500 transition-all placeholder:text-slate-400">
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block px-1">Custo (Opcional)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xs font-black text-slate-400">R$</span>
                                <input v-model="paymentForm.cost" type="number" step="0.01" placeholder="0,00"
                                    class="w-full bg-slate-50 dark:bg-slate-800/50 border-0 rounded-2xl pl-10 pr-4 py-4 text-sm font-black text-slate-800 dark:text-white focus:ring-2 focus:ring-green-500 transition-all placeholder:text-slate-400">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-8 bg-slate-50/50 dark:bg-slate-800/50 border-t border-gray-100 dark:border-slate-800">
                    <button @click="submitPayment" :disabled="isGeneratingPayment || !paymentForm.amount || !paymentForm.description"
                        class="w-full bg-green-500 hover:bg-green-600 text-white font-black py-4 px-6 rounded-2xl flex items-center justify-center gap-2 shadow-xl shadow-green-500/30 transition-all active:scale-95 disabled:opacity-50 disabled:grayscale">
                        <span v-if="!isGeneratingPayment">GERAR LINK DE PAGAMENTO</span>
                        <div v-else class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                    </button>
                    <p class="text-[9px] font-bold text-center text-slate-400 mt-4 uppercase tracking-widest leading-relaxed">O link será gerado e inserido automaticamente no campo de mensagem.</p>
                </div>
            </div>
        </div>
    </div>
</template>
