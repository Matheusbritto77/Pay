<script setup>
import { computed, watch } from 'vue';

const props = defineProps({
    show: Boolean,
    leadForm: Object,
    pipelines: Array,
});

const emit = defineEmits(['close', 'submit']);

const currentPipeline = computed(() => props.pipelines?.find(p => p.id === props.leadForm.pipeline_id));

// Reset stage when pipeline changes
watch(() => props.leadForm.pipeline_id, (newVal) => {
    if (newVal) {
        const pipeline = props.pipelines.find(p => p.id === newVal);
        if (pipeline && pipeline.stages?.length > 0) {
            props.leadForm.stage_id = pipeline.stages[0].id;
        }
    }
});
</script>

<template>
    <Teleport to="body">
        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
            <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-6">
                <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-md" @click="emit('close')"></div>
                <div class="relative bg-white dark:bg-slate-900 rounded-[40px] shadow-2xl p-10 w-full max-w-xl border border-gray-100 dark:border-slate-800 overflow-hidden">
                    <div class="absolute -top-12 -right-12 w-48 h-48 bg-indigo-500/10 rounded-full blur-3xl"></div>
                    <h3 class="text-3xl font-black text-slate-800 dark:text-white mb-8">Criar Novo Lead</h3>
                    <form @submit.prevent="emit('submit')" class="grid grid-cols-2 gap-6">
                        <div class="col-span-2">
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-2">Nome do Lead / Deal</label>
                            <input v-model="leadForm.name" type="text" required class="w-full h-14 bg-slate-50 dark:bg-slate-800 border-none rounded-2xl px-6 font-bold focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        
                        <!-- Pipeline Selection -->
                        <div v-if="pipelines?.length > 1" class="col-span-2">
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-2">Funil de Vendas</label>
                            <select v-model="leadForm.pipeline_id" class="w-full h-14 bg-slate-50 dark:bg-slate-800 border-none rounded-2xl px-6 font-bold">
                                <option v-for="p in pipelines" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>

                        <!-- Stage Selection -->
                        <div class="col-span-2">
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-2">Estágio Inicial</label>
                            <select v-model="leadForm.stage_id" required class="w-full h-14 bg-slate-50 dark:bg-slate-800 border-none rounded-2xl px-6 font-bold">
                                <option v-for="s in currentPipeline?.stages" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-2">Valor Estimado</label>
                            <input v-model="leadForm.value" type="number" step="0.01" class="w-full h-14 bg-slate-50 dark:bg-slate-800 border-none rounded-2xl px-6 font-bold" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-2">Origem</label>
                            <select v-model="leadForm.source" class="w-full h-14 bg-slate-50 dark:bg-slate-800 border-none rounded-2xl px-6 font-bold">
                                <option value="manual">Manual</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="site">Site</option>
                            </select>
                        </div>
                        <div class="col-span-2 flex gap-4 mt-4">
                            <button type="button" @click="emit('close')" class="flex-1 h-16 bg-slate-50 dark:bg-slate-800 text-slate-600 font-black rounded-3xl hover:bg-slate-100 transition-all">Cancelar</button>
                            <button type="submit" :disabled="leadForm.processing" class="flex-2 h-16 px-12 bg-indigo-600 text-white font-black rounded-3xl shadow-xl shadow-indigo-500/30 hover:shadow-indigo-500/50 hover:scale-105 active:scale-95 transition-all">Começar Negócio</button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
