<script setup>
import { Link, router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

defineProps({
    show: Boolean,
});

defineEmits(['close']);

const switchToTeam = (team) => {
    router.put(route('current-team.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};
</script>

<template>
    <Modal :show="show" @close="$emit('close')" maxWidth="lg">
        <div class="p-8">
            <div class="flex items-center gap-4 mb-10">
                <div class="size-14 rounded-2xl bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                    <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a5.971 5.971 0 00-.94 3.197m0 0a5.971 5.971 0 00.94 3.198l.001.031c0 .225.012.447.038.666A11.944 11.944 0 0112 21c2.17 0 4.207-.576 5.963-1.584A6.062 6.062 0 0118 18.722zm-12 0a5.971 5.971 0 00.941-3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white leading-tight">Gerenciar Equipes</h2>
                    <p class="text-slate-500 dark:text-slate-400 font-medium">Troque de time ou gerencie configurações</p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Current Team Actions -->
                <div>
                    <div class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-[0.2em] mb-4 px-2">Time Atual: {{ $page.props.auth.user.current_team.name }}</div>
                    <div class="grid grid-cols-1 gap-3">
                        <Link :href="route('teams.show', $page.props.auth.user.current_team)" @click="$emit('close')" class="flex items-center gap-4 px-5 py-4 rounded-2xl hover:bg-gray-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 transition-all duration-200 group border border-transparent hover:border-gray-100 dark:hover:border-slate-700">
                            <div class="size-11 rounded-xl bg-indigo-100/50 dark:bg-indigo-900/30 flex items-center justify-center group-hover:bg-indigo-100 dark:group-hover:bg-indigo-900/50 transition-colors">
                                <svg class="size-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0015 0m-15 0a7.5 7.5 0 1115 0m-15 0H3m16.5 0H21m-1.5 0H12m-.75-4.5l3 3m0 0l-3 3m3-3H9" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-base leading-snug">Configurações do Time</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Gerenciar membros e recursos</p>
                            </div>
                        </Link>

                        <Link v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')" @click="$emit('close')" class="flex items-center gap-4 px-5 py-4 rounded-2xl hover:bg-gray-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 transition-all duration-200 group border border-transparent hover:border-gray-100 dark:hover:border-slate-700">
                            <div class="size-11 rounded-xl bg-emerald-100/50 dark:bg-emerald-900/30 flex items-center justify-center group-hover:bg-emerald-100 dark:group-hover:bg-emerald-900/50 transition-colors">
                                <svg class="size-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-base leading-snug">Criar Novo Time</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">Expandir sua organização</p>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Switch Teams List -->
                <div v-if="$page.props.auth.user.all_teams.length > 1" class="pt-6 border-t border-gray-100 dark:border-slate-800">
                    <div class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-[0.2em] mb-4 px-2">Trocar de Time</div>
                    <div class="space-y-2 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                        <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                            <button 
                                @click="switchToTeam(team); $emit('close')"
                                class="w-full flex items-center justify-between p-4 rounded-2xl transition-all duration-200 group text-left border"
                                :class="team.id == $page.props.auth.user.current_team_id 
                                    ? 'bg-indigo-50/50 dark:bg-indigo-900/20 border-indigo-100 dark:border-indigo-900/50' 
                                    : 'hover:bg-gray-50 dark:hover:bg-slate-800 border-transparent hover:border-gray-100 dark:hover:border-slate-700'"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="size-10 rounded-full flex items-center justify-center font-bold text-sm transition-colors"
                                        :class="team.id == $page.props.auth.user.current_team_id
                                            ? 'bg-indigo-100 dark:bg-indigo-800 text-indigo-700 dark:text-indigo-300'
                                            : 'bg-gray-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 group-hover:bg-gray-200 dark:group-hover:bg-slate-700'"
                                    >
                                        {{ team.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 dark:text-slate-200" :class="{ 'text-indigo-600 dark:text-indigo-400': team.id == $page.props.auth.user.current_team_id }">
                                            {{ team.name }}
                                        </p>
                                        <p class="text-xs text-slate-500">Membro da Equipe</p>
                                    </div>
                                </div>
                                <svg v-if="team.id == $page.props.auth.user.current_team_id" class="size-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <button @click="$emit('close')" class="mt-10 w-full py-4 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-2xl font-bold transition-all duration-200 shadow-sm">
                Voltar ao Painel
            </button>
        </div>
    </Modal>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #1e293b;
}
</style>
