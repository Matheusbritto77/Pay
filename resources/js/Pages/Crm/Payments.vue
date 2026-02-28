<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    payments: Object,
    filters: Object
});

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');

const debounce = (fn, delay) => {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn(...args), delay);
    };
};

const updateFilters = debounce(() => {
    router.get(route('crm.payments.index'), {
        search: search.value,
        status: statusFilter.value
    }, {
        preserveState: true,
        replace: true
    });
}, 300);

watch([search, statusFilter], () => {
    updateFilters();
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getStatusBadgeClass = (status) => {
    switch (status) {
        case 'paid':
            return 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400';
        case 'pending':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400';
        case 'cancelled':
            return 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-500/10 dark:text-slate-400';
    }
};

const getStatusLabel = (status) => {
    switch (status) {
        case 'paid': return 'Pago';
        case 'pending': return 'Pendente';
        case 'cancelled': return 'Cancelado';
        case 'expired': return 'Expirado';
        default: return status;
    }
};

const resendingId = ref(null);
const isProcessing = computed(() => resendingId.value !== null);

const resendPayment = (paymentId) => {
    resendingId.value = paymentId;
    router.post(route('crm.payments.resend', paymentId), {}, {
        onFinish: () => {
            resendingId.value = null;
        }
    });
};

</script>

<template>
    <AppLayout title="Gestão de Pagamentos">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-black text-2xl text-slate-800 dark:text-white uppercase tracking-tight">
                    Gestão de Pagamentos
                </h2>
                <div class="flex items-center gap-3">
                    <span class="px-3 py-1 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-full text-xs font-black uppercase tracking-widest border border-indigo-100 dark:border-indigo-500/20">
                        {{ payments.total }} Cobranças
                    </span>
                </div>
            </div>
        </template>

        <div class="space-y-6 relative">
            <!-- Loading Overlay -->
            <div v-if="isProcessing" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/20 backdrop-blur-sm transition-all duration-300">
                <div class="bg-white dark:bg-slate-900 p-8 rounded-[2.5rem] shadow-2xl flex flex-col items-center gap-4 border border-slate-100 dark:border-slate-800">
                    <div class="size-16 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div>
                    <p class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-widest">Enviando cobrança...</p>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-[2.5rem] shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-[300px] relative">
                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input 
                        type="text" 
                        v-model="search"
                        placeholder="Buscar por descrição ou contato..."
                        class="w-full pl-11 pr-4 py-3 bg-slate-50 dark:bg-slate-800/50 border-none rounded-2xl text-sm font-bold text-slate-700 dark:text-slate-200 focus:ring-2 focus:ring-indigo-500 transition-all"
                    >
                </div>
                
                <div class="flex items-center gap-3">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Status:</span>
                    <select 
                        v-model="statusFilter"
                        class="bg-slate-50 dark:bg-slate-800/50 border-none rounded-2xl text-xs font-black uppercase tracking-widest px-6 py-3 focus:ring-2 focus:ring-indigo-500 transition-all text-slate-700 dark:text-slate-200"
                    >
                        <option value="all">Todos</option>
                        <option value="pending">Pendentes</option>
                        <option value="paid">Pagos</option>
                        <option value="cancelled">Cancelados</option>
                    </select>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-900 rounded-[2.5rem] shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden transition-all duration-300">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Data</th>
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Contato / Lead</th>
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Descrição</th>
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Valor</th>
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                            <tr v-for="payment in payments.data" :key="payment.id" class="group hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="px-6 py-5">
                                    <div class="text-xs font-bold text-slate-500 dark:text-slate-400">{{ formatDate(payment.created_at) }}</div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-black text-slate-800 dark:text-white">{{ payment.contact?.name }}</span>
                                        <span v-if="payment.lead" class="text-[10px] font-bold text-indigo-500 uppercase tracking-tight">{{ payment.lead.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="text-xs font-bold text-slate-600 dark:text-slate-300 truncate max-w-xs">{{ payment.description }}</div>
                                </td>
                                <td class="px-6 py-5 text-right font-black text-sm text-slate-800 dark:text-white">
                                    {{ formatCurrency(payment.amount) }}
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex justify-center">
                                        <span :class="getStatusBadgeClass(payment.status)" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest transition-all">
                                            {{ getStatusLabel(payment.status) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a v-if="payment.payment_url && payment.status === 'pending'" 
                                           :href="payment.payment_url" target="_blank"
                                           class="p-2 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-xl hover:bg-indigo-600 hover:text-white transition-all shadow-sm"
                                           title="Ver Link">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </a>
                                        <button v-if="payment.status === 'pending'"
                                            @click="resendPayment(payment.id)"
                                            :disabled="resendingId === payment.id"
                                            class="p-2 bg-green-50 dark:bg-green-500/10 text-green-600 dark:text-green-400 rounded-xl hover:bg-green-600 hover:text-white transition-all shadow-sm disabled:opacity-50"
                                            title="Cobrar Novamente">
                                            <svg v-if="resendingId !== payment.id" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                                            <div v-else class="w-4 h-4 border-2 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="payments.data.length === 0">
                                <td colspan="6" class="px-6 py-20 text-center">
                                    <div class="flex flex-col items-center gap-4">
                                        <div class="p-6 bg-slate-50 dark:bg-slate-800/50 rounded-[2rem] border border-dashed border-slate-200 dark:border-slate-800">
                                            <svg class="w-12 h-12 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-wider">Nenhuma cobrança emitida</p>
                                            <p class="text-xs font-bold text-slate-400 mt-1">Gere novos links de pagamento através do Inbox do CRM.</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Simple Pagination -->
                <div v-if="payments.last_page > 1" class="px-6 py-6 bg-slate-50/50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <div class="text-xs font-bold text-slate-500">
                        Mostrando {{ payments.from }} a {{ payments.to }} de {{ payments.total }} cobranças
                    </div>
                    <div class="flex gap-2">
                        <Link v-for="link in payments.links" 
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            :class="[
                                'px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all border',
                                link.active ? 'bg-indigo-600 text-white border-indigo-600 shadow-lg shadow-indigo-500/30' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-800 hover:border-indigo-500'
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
