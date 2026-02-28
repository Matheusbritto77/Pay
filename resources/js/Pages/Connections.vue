<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const props = defineProps({
    instances: Array,
    teamId: Number,
});

const isCreating = ref(false);
const connectingIds = ref(new Set());
const localInstances = ref([...props.instances]);

const createForm = useForm({});

// Real-time updates via Echo
let echoChannel = null;

onMounted(() => {
    if (window.Echo && props.teamId) {
        echoChannel = window.Echo.private(`team.${props.teamId}.whatsapp`);
        echoChannel.listen('.status.updated', (e) => {
            const idx = localInstances.value.findIndex(i => i.id === e.instance_id);
            if (idx !== -1) {
                localInstances.value[idx] = {
                    ...localInstances.value[idx],
                    status: e.status,
                    qr_code: e.qr_code,
                    phone: e.phone,
                };
            }
        });
    }
});

onUnmounted(() => {
    if (echoChannel) {
        echoChannel.stopListening('.status.updated');
        window.Echo?.leave(`team.${props.teamId}.whatsapp`);
    }
});

const createInstance = () => {
    isCreating.value = true;
    createForm.post(route('connections.store'), {
        onSuccess: () => {
            isCreating.value = false;
            router.reload({ only: ['instances'] });
        },
        onError: () => {
            isCreating.value = false;
        },
    });
};

const connectInstance = (id) => {
    connectingIds.value.add(id);
    // Immediately update local state to show loading
    const idx = localInstances.value.findIndex(i => i.id === id);
    if (idx !== -1) {
        localInstances.value[idx] = { ...localInstances.value[idx], status: 'connecting' };
    }
    router.post(route('connections.connect', id), {}, {
        preserveScroll: true,
        onFinish: () => {
            connectingIds.value.delete(id);
        },
    });
};

const disconnectInstance = (id) => {
    router.post(route('connections.disconnect', id));
};

const deleteInstance = (id) => {
    if (confirm('Tem certeza que deseja excluir esta instância? Esta ação é irreversível.')) {
        router.delete(route('connections.destroy', id));
    }
};

const statusConfig = {
    disconnected: { label: 'Disconnected', color: '#64748b', bg: 'rgba(100,116,139,0.1)', icon: '○' },
    connecting: { label: 'Connecting...', color: '#f59e0b', bg: 'rgba(245,158,11,0.1)', icon: '◌' },
    qr_pending: { label: 'Scan QR', color: '#8b5cf6', bg: 'rgba(139,92,246,0.1)', icon: '◎' },
    connected: { label: 'Connected', color: '#22c55e', bg: 'rgba(34,197,94,0.1)', icon: '●' },
};

// Watch for prop changes (Inertia reload)
import { watch } from 'vue';
watch(() => props.instances, (val) => {
    localInstances.value = [...val];
});
</script>

<template>
    <AppLayout title="Connections">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-white leading-tight">Connections</h2>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header Bar -->
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900 dark:text-white">WhatsApp Instances</h3>
                        <p class="text-slate-500 dark:text-slate-400 mt-1">Manage your team's WhatsApp connections</p>
                    </div>
                    <button
                        @click="createInstance"
                        :disabled="isCreating"
                        class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold rounded-xl shadow-lg shadow-green-500/25 hover:shadow-green-500/40 hover:-translate-y-0.5 active:translate-y-0 transition-all flex items-center gap-2 disabled:opacity-50"
                    >
                        <svg v-if="!isCreating" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        <svg v-else class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                        {{ isCreating ? 'Creating...' : 'New Connection' }}
                    </button>
                </div>

                <!-- Empty State -->
                <div v-if="localInstances.length === 0" class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-slate-800 p-16 text-center">
                    <div class="w-20 h-20 mx-auto mb-6 bg-green-50 dark:bg-green-900/20 rounded-full flex items-center justify-center">
                        <svg class="w-10 h-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                    </div>
                    <h4 class="text-lg font-bold text-slate-900 dark:text-white mb-2">No connections yet</h4>
                    <p class="text-slate-500 dark:text-slate-400 mb-6">Create your first WhatsApp connection to get started</p>
                    <button @click="createInstance" :disabled="isCreating" class="px-6 py-3 bg-green-500 text-white font-bold rounded-xl hover:bg-green-600 transition-colors disabled:opacity-50">
                        {{ isCreating ? 'Creating...' : 'Create First Connection' }}
                    </button>
                </div>

                <!-- Instance Cards Grid -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <div
                        v-for="instance in localInstances"
                        :key="instance.id"
                        class="bg-white dark:bg-slate-900 rounded-2xl border border-gray-200 dark:border-slate-800 overflow-hidden hover:shadow-xl hover:shadow-slate-200/50 dark:hover:shadow-slate-900/50 transition-all duration-300 group"
                    >
                        <!-- Card Header -->
                        <div class="p-6 pb-4">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl" :style="{ backgroundColor: statusConfig[instance.status]?.bg }">
                                        <svg class="w-6 h-6" :style="{ color: statusConfig[instance.status]?.color }" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-slate-900 dark:text-white">{{ instance.name }}</h4>
                                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ instance.phone || 'No phone yet' }}</p>
                                    </div>
                                </div>
                                <!-- Status Badge -->
                                <span class="px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1.5"
                                    :style="{ color: statusConfig[instance.status]?.color, backgroundColor: statusConfig[instance.status]?.bg }">
                                    <span class="text-[10px]" :class="{ 'animate-pulse': instance.status === 'connecting' || instance.status === 'qr_pending' }">{{ statusConfig[instance.status]?.icon }}</span>
                                    {{ statusConfig[instance.status]?.label }}
                                </span>
                            </div>
                        </div>

                        <!-- QR Code Area -->
                        <div v-if="instance.status === 'qr_pending' && instance.qr_code" class="px-6 pb-4">
                            <div class="bg-white rounded-xl p-4 flex items-center justify-center border-2 border-dashed border-purple-200 dark:border-purple-900">
                                <img :src="instance.qr_code" alt="QR Code" class="w-48 h-48 object-contain" />
                            </div>
                            <p class="text-center text-xs text-purple-500 font-medium mt-2 animate-pulse">Scan with WhatsApp to connect</p>
                        </div>

                        <!-- Connecting animation -->
                        <div v-if="instance.status === 'connecting'" class="px-6 pb-4">
                            <div class="bg-amber-50 dark:bg-amber-900/10 rounded-xl p-6 flex flex-col items-center gap-2">
                                <div class="flex gap-1">
                                    <span v-for="i in 3" :key="i" class="w-3 h-3 bg-amber-400 rounded-full animate-bounce" :style="{ animationDelay: `${i * 0.15}s` }"></span>
                                </div>
                                <p class="text-xs text-amber-600 dark:text-amber-400 font-medium">Establishing connection...</p>
                            </div>
                        </div>

                        <!-- Card Actions -->
                        <div class="px-6 py-4 border-t border-gray-100 dark:border-slate-800 flex gap-2">
                            <template v-if="instance.status === 'disconnected'">
                                <button @click="connectInstance(instance.id)" :disabled="connectingIds.has(instance.id)" class="flex-1 py-2.5 bg-green-500 hover:bg-green-600 text-white text-sm font-bold rounded-xl transition-colors flex items-center justify-center gap-2 disabled:opacity-70">
                                    <svg v-if="connectingIds.has(instance.id)" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" /></svg>
                                    {{ connectingIds.has(instance.id) ? 'Connecting...' : 'Connect' }}
                                </button>
                            </template>
                            <template v-else-if="instance.status === 'connected'">
                                <button @click="disconnectInstance(instance.id)" class="flex-1 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-bold rounded-xl transition-colors">
                                    Disconnect
                                </button>
                            </template>
                            <template v-else-if="instance.status === 'qr_pending'">
                                <button @click="disconnectInstance(instance.id)" class="flex-1 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-sm font-bold rounded-xl transition-colors">
                                    Cancel
                                </button>
                            </template>
                            <template v-else>
                                <div class="flex-1 py-2.5 text-center text-sm text-slate-400 font-medium">Waiting...</div>
                            </template>
                            <button @click="deleteInstance(instance.id)" class="py-2.5 px-4 bg-red-50 dark:bg-red-900/10 hover:bg-red-100 dark:hover:bg-red-900/20 text-red-500 text-sm font-bold rounded-xl transition-colors" title="Delete">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </AppLayout>
</template>
