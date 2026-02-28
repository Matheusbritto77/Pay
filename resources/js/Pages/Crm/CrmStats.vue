<script setup>
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    waInstances: Array,
});

const formatValue = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
</script>

<template>
    <div class="px-6 py-3 flex items-center gap-6 bg-slate-50/50 dark:bg-slate-900/30 backdrop-blur-md border-b border-gray-200 dark:border-slate-800/50">
        <!-- Pipeline Stats -->
        <div class="flex items-center gap-8 border-r border-gray-200 dark:border-slate-800 pr-8">
            <div class="flex flex-col">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Pipeline</span>
                <span class="text-sm font-black text-indigo-600 dark:text-indigo-400">{{ formatValue(props.stats?.total_value) }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Leads Ativos</span>
                <span class="text-sm font-black text-slate-700 dark:text-white">{{ props.stats?.by_status?.active || 0 }}</span>
            </div>
            <div class="flex flex-col">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Ticket Médio</span>
                <span class="text-sm font-bold text-slate-700 dark:text-white">{{ formatValue(props.stats?.avg_value) }}</span>
            </div>
        </div>

        <!-- WhatsApp Status -->
        <div class="flex items-center gap-3 overflow-x-auto scroller-hidden">
            <div v-for="inst in waInstances" :key="inst.id" 
                class="flex items-center gap-2 px-3 py-1.5 rounded-xl border text-[11px] font-bold transition-all shadow-sm shrink-0"
                :class="inst.status === 'connected' ? 'bg-green-50/50 border-green-200 text-green-700 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400' : 'bg-red-50/50 border-red-200 text-red-600 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400'">
                <span class="relative flex h-1.5 w-1.5">
                    <span v-if="inst.status === 'connected'" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-1.5 w-1.5" :class="inst.status === 'connected' ? 'bg-green-500' : 'bg-red-500'"></span>
                </span>
                {{ inst.name }}
            </div>
        </div>

        <a :href="route('connections')" class="ml-auto p-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl hover:shadow-md transition-all group" title="Conexões">
            <svg class="w-4 h-4 text-slate-500 group-hover:text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
        </a>
    </div>
</template>
