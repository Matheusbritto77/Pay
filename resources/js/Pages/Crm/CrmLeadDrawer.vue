<script setup>
import { ref } from 'vue';

const props = defineProps({
    show: Boolean,
    lead: Object,
    tab: String,
    activityFeed: Array,
    tags: Array,
    localTags: Array,
    showTagPicker: Boolean,
    newTagForm: Object,
    newNote: String,
    newTaskTitle: String,
});

const emit = defineEmits([
    'close', 
    'update:tab', 
    'delete', 
    'add-note', 
    'add-task', 
    'toggle-task', 
    'toggle-tag', 
    'create-tag',
    'toggle-tag-picker',
    'update:newNote',
    'update:newTaskTitle'
]);

const formatValue = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);

function timeAgo(date) {
    if (!date) return '';
    const diff = Math.floor((new Date() - new Date(date)) / 1000);
    if (diff < 60) return 'agora';
    if (diff < 3600) return `${Math.floor(diff/60)}m`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h`;
    return `${Math.floor(diff/86400)}d`;
}

function getActivityIcon(type) {
    const icons = { stage_change: '🚀', value_change: '💰', status_change: '⚡', lead_created: '✨', task_completed: '✅' };
    return icons[type] || '📝';
}
</script>

<template>
    <transition enter-active-class="transform transition ease-in-out duration-300" enter-from-class="translate-x-full" enter-to-class="translate-x-0" leave-active-class="transform transition ease-in-out duration-200" leave-from-class="translate-x-0" leave-to-class="translate-x-full">
        <div v-if="show && lead" class="fixed inset-y-0 right-0 z-[100] w-full max-w-lg bg-white dark:bg-slate-900 shadow-2xl flex flex-col border-l border-gray-100 dark:border-slate-800">
            <div class="p-8 border-b border-gray-100 dark:border-slate-800 flex justify-between items-start">
                <div class="flex-1 min-w-0">
                    <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 dark:bg-indigo-500/10 px-2 py-1 rounded-lg uppercase mb-2 inline-block">Lead Detalhes</span>
                    <h3 class="text-2xl font-black text-slate-800 dark:text-white truncate">{{ lead.name }}</h3>
                    <div class="flex items-center gap-4 mt-1 text-sm font-bold text-slate-400">
                        <span>{{ formatValue(lead.value) }}</span>
                        <span class="w-1.5 h-1.5 bg-slate-300 dark:bg-slate-700 rounded-full"></span>
                        <span :style="{ color: lead.stage?.color }">{{ lead.stage?.name }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button @click="emit('delete', lead.id)" class="w-10 h-10 bg-red-50 dark:bg-red-500/10 text-red-500 rounded-xl flex items-center justify-center hover:bg-red-500 hover:text-white transition-all"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button>
                    <button @click="emit('close')" class="w-10 h-10 bg-slate-50 dark:bg-slate-800 text-slate-500 rounded-xl flex items-center justify-center hover:bg-indigo-600 hover:text-white transition-all"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
            </div>

            <div class="flex-1 flex flex-col overflow-hidden">
                <div class="px-8 flex border-b border-gray-100 dark:border-slate-800">
                    <button v-for="t in [{id:'activity', label:'Timeline'},{id:'info', label:'Informações'}]" :key="t.id" @click="emit('update:tab', t.id)"
                        :class="tab === t.id ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-400'"
                        class="px-4 py-4 text-xs font-black uppercase tracking-widest border-b-2 transition-all">
                        {{ t.label }}
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto custom-scrollbar">
                    <div v-if="tab === 'activity'" class="p-8 space-y-8">
                        <div class="bg-indigo-500 text-white rounded-[28px] p-6 shadow-xl shadow-indigo-500/30">
                            <h4 class="text-xs font-black uppercase tracking-widest mb-4 opacity-80">Rápida Ação</h4>
                            <form @submit.prevent="emit('add-note')" class="relative">
                                <textarea :value="newNote" @input="emit('update:newNote', $event.target.value)" placeholder="Escreva uma nota ou observação..." rows="3" class="w-full bg-white/20 border-white/20 rounded-2xl text-sm placeholder-white/60 focus:ring-white/50 focus:border-white/50 text-white p-4 resize-none"></textarea>
                                <button type="submit" class="mt-4 w-full h-11 bg-white text-indigo-600 font-black rounded-xl hover:shadow-lg hover:-translate-y-0.5 transition-all">Salvar Nota</button>
                            </form>
                        </div>

                        <div class="relative pl-8 space-y-8">
                            <div class="absolute left-[3px] top-4 bottom-4 w-0.5 bg-slate-100 dark:bg-slate-800"></div>
                            <div v-for="item in activityFeed" :key="item.id" class="relative">
                                <div class="absolute -left-10 top-0 w-6 h-6 bg-white dark:bg-slate-900 border-2 border-slate-100 dark:border-slate-800 rounded-full flex items-center justify-center text-[10px] z-10 shadow-sm">
                                    {{ item.feedType === 'note' ? '💬' : getActivityIcon(item.type) }}
                                </div>
                                <div :class="item.feedType === 'note' ? 'bg-amber-50/50 dark:bg-amber-500/5 border-amber-200/50' : 'bg-slate-50/50 dark:bg-slate-800/30 border-slate-200/50 dark:border-slate-800/50'"
                                    class="p-5 rounded-3xl border shadow-sm">
                                    <div class="flex justify-between items-start mb-2">
                                        <h5 class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ item.user?.name || 'Sistema' }}</h5>
                                        <span class="text-[9px] font-bold text-slate-400 opacity-70">{{ timeAgo(item.created_at) }}</span>
                                    </div>
                                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200 leading-relaxed">{{ item.description || item.content }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="tab === 'info'" class="p-8 space-y-6">
                        <div class="bg-slate-50 dark:bg-slate-800/40 p-6 rounded-[32px] space-y-4">
                            <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-2">Lead Info</h4>
                            <div class="flex flex-col gap-1">
                                <span class="text-[10px] font-bold opacity-50 uppercase">Empresa</span>
                                <input :value="lead.contact?.company" class="bg-transparent border-none p-0 text-sm font-bold text-slate-800 dark:text-white focus:ring-0" placeholder="Sem empresa..." />
                            </div>
                            <div class="flex flex-col gap-1">
                                <span class="text-[10px] font-bold opacity-50 uppercase">Origem</span>
                                <span class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                                    {{ lead.source === 'whatsapp' ? '🟢 WhatsApp' : '📝 Manual' }}
                                </span>
                            </div>
                            <div class="flex flex-col gap-1 pt-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-bold opacity-50 uppercase">Tags do Lead</span>
                                    <div class="relative">
                                        <button @click="emit('toggle-tag-picker')" class="w-6 h-6 bg-indigo-500/10 text-indigo-500 rounded-lg flex items-center justify-center hover:bg-indigo-500 hover:text-white transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M12 4v16m8-8H4"/></svg>
                                        </button>
                                        
                                        <div v-if="showTagPicker" class="absolute right-0 top-full mt-2 w-64 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-gray-100 dark:border-slate-700 z-[110] p-4 space-y-4">
                                            <div class="space-y-2">
                                                <p class="text-[10px] font-black uppercase text-slate-400">Selecionar Tags</p>
                                                <div class="max-h-40 overflow-y-auto custom-scrollbar flex flex-wrap gap-1.5 p-1">
                                                    <button v-for="tag in localTags" :key="tag.id" @click="emit('toggle-tag', tag)"
                                                        :class="lead.tags?.find(t => t.id === tag.id) ? 'ring-2 ring-indigo-500 opacity-100' : 'opacity-60 hover:opacity-100'"
                                                        class="px-2.5 py-1 rounded-lg text-[10px] font-black text-white transition-all"
                                                        :style="{ backgroundColor: tag.color }">
                                                        {{ tag.name }}
                                                    </button>
                                                    <p v-if="localTags.length === 0" class="text-[10px] font-bold text-slate-400 p-2">Nenhuma tag criada.</p>
                                                </div>
                                            </div>
                                            
                                            <div class="pt-4 border-t border-gray-100 dark:border-slate-700 space-y-2">
                                                <p class="text-[10px] font-black uppercase text-slate-400">Nova Tag</p>
                                                <div class="flex gap-2">
                                                    <input :value="newTagForm.name" @input="newTagForm.name = $event.target.value" type="text" placeholder="Nome..." class="flex-1 h-8 bg-slate-50 dark:bg-slate-900 border-none rounded-lg text-[10px] px-2 focus:ring-1 focus:ring-indigo-500 shadow-inner" />
                                                    <input :value="newTagForm.color" @input="newTagForm.color = $event.target.value" type="color" class="w-8 h-8 rounded-lg overflow-hidden border-none p-0 cursor-pointer" />
                                                    <button @click="emit('create-tag')" :disabled="newTagForm.processing" class="px-3 h-8 bg-indigo-600 text-white rounded-lg text-[10px] font-bold disabled:opacity-50">Criar</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="tag in lead.tags" :key="tag.id" class="group relative px-3 py-1 bg-slate-200 dark:bg-slate-700 rounded-lg text-xs font-bold cursor-default hover:bg-slate-300 dark:hover:bg-slate-600 transition-all" :style="{ borderLeft: `3px solid ${tag.color}` }">
                                        {{ tag.name }}
                                        <button @click="emit('toggle-tag', tag)" class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all transform hover:scale-110 shadow-lg">
                                            <svg class="w-2 h-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4"><path d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </span>
                                    <div v-if="!lead.tags?.length" class="text-[10px] font-bold text-slate-400 italic">Nenhuma tag vinculada</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 bg-slate-50 dark:bg-slate-800/40 rounded-[32px]">
                            <h4 class="text-xs font-black uppercase tracking-widest text-slate-400 mb-4">Checklist de Tarefas</h4>
                            <div v-for="task in (lead.tasks || [])" :key="task.id" class="flex items-center gap-4 group p-2 hover:bg-white dark:hover:bg-slate-700/50 rounded-2xl transition-all">
                                <button @click="emit('toggle-task', task)" :class="task.completed_at ? 'bg-green-500 border-green-500 text-white' : 'bg-transparent border-slate-300 dark:border-slate-600'" class="w-6 h-6 rounded-xl border-2 flex items-center justify-center transition-all">
                                    <svg v-if="task.completed_at" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M5 13l4 4L19 7"/></svg>
                                </button>
                                <span :class="{ 'line-through opacity-40': task.completed_at }" class="text-sm font-bold text-slate-700 dark:text-slate-200 flex-1">{{ task.title }}</span>
                            </div>
                            <form @submit.prevent="emit('add-task')" class="mt-4 flex gap-2">
                                <input :value="newTaskTitle" @input="emit('update:newTaskTitle', $event.target.value)" type="text" placeholder="Próxima ação..." class="flex-1 bg-white/50 dark:bg-slate-900/50 border-none rounded-2xl h-11 text-sm px-4 focus:ring-2 focus:ring-indigo-500" />
                                <button type="submit" class="w-11 h-11 bg-indigo-600 text-white rounded-2xl flex items-center justify-center"><svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </transition>
    <div v-if="show" class="fixed inset-0 z-[99] bg-slate-950/40 backdrop-blur-sm transition-all" @click="emit('close')"></div>
</template>
