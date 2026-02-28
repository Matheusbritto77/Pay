<script setup>
import { Link } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

defineProps({
    show: Boolean,
});

defineEmits(['close']);

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Modal :show="show" @close="$emit('close')" maxWidth="lg">
        <div class="p-8">
            <div class="flex items-center gap-6 mb-10">
                <img v-if="$page.props.jetstream.managesProfilePhotos" class="size-20 rounded-full object-cover border-4 border-indigo-500/20 shadow-lg" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.name">
                <div v-else class="size-20 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center font-bold text-indigo-700 dark:text-indigo-300 text-3xl border-4 border-indigo-500/20 shadow-lg">
                    {{ $page.props.auth.user.name.charAt(0) }}
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white leading-tight">{{ $page.props.auth.user.name }}</h2>
                    <p class="text-slate-500 dark:text-slate-400 font-medium">{{ $page.props.auth.user.email }}</p>
                </div>
            </div>

            <div class="space-y-2">
                <div class="text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-[0.2em] mb-6 px-2">Configurações da Conta</div>
                
                <Link :href="route('profile.show')" @click="$emit('close')" class="flex items-center gap-4 px-5 py-4 rounded-2xl hover:bg-gray-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 transition-all duration-200 group border border-transparent hover:border-gray-100 dark:hover:border-slate-700">
                    <div class="size-12 rounded-xl bg-blue-100/50 dark:bg-blue-900/30 flex items-center justify-center group-hover:bg-blue-100 dark:group-hover:bg-blue-900/50 transition-colors">
                        <svg class="size-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-lg leading-snug">Meu Perfil</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Ver e atualizar suas informações pessoais</p>
                    </div>
                </Link>

                <Link v-if="$page.props.jetstream.hasApiFeatures" :href="route('api-tokens.index')" @click="$emit('close')" class="flex items-center gap-4 px-5 py-4 rounded-2xl hover:bg-gray-50 dark:hover:bg-slate-800/80 text-slate-700 dark:text-slate-200 transition-all duration-200 group border border-transparent hover:border-gray-100 dark:hover:border-slate-700">
                    <div class="size-12 rounded-xl bg-amber-100/50 dark:bg-amber-900/30 flex items-center justify-center group-hover:bg-amber-100 dark:group-hover:bg-amber-900/50 transition-colors">
                        <svg class="size-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="font-bold text-lg leading-snug">Tokens de API</p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Genenciar suas chaves de acesso externas</p>
                    </div>
                </Link>

                <div class="pt-6 mt-6 border-t border-gray-100 dark:border-slate-800">
                    <form @submit.prevent="router.post(route('logout'))">
                        <button type="submit" class="w-full flex items-center gap-4 px-5 py-4 rounded-2xl hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 transition-all duration-200 group text-left border border-transparent hover:border-red-100 dark:hover:border-red-900/30">
                            <div class="size-12 rounded-xl bg-red-100/50 dark:bg-red-900/30 flex items-center justify-center group-hover:bg-red-100 dark:group-hover:bg-red-900/50 transition-colors">
                                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-lg leading-snug">Encerrar Sessão</p>
                                <p class="text-sm opacity-60">Logout imediato e seguro</p>
                            </div>
                        </button>
                    </form>
                </div>
            </div>

            <button @click="$emit('close')" class="mt-10 w-full py-4 bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-2xl font-bold transition-all duration-200 shadow-sm">
                Voltar ao Painel
            </button>
        </div>
    </Modal>
</template>

<script>
import { router } from '@inertiajs/vue3';
export default {
    inheritAttrs: false
}
</script>
