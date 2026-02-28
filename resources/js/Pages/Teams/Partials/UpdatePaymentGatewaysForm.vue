<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ActionMessage from '@/Components/ActionMessage.vue';
import FormSection from '@/Components/FormSection.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    team: Object,
});

const activeTab = ref('mercadopago');

const form = useForm({
    mercado_pago: {
        public_key: props.team.payment_settings?.mercado_pago?.public_key || '',
        access_token: props.team.payment_settings?.mercado_pago?.access_token || '',
    },
    payment_message: props.team.payment_settings?.payment_message || "Olá! Segue o link para pagamento ref. {description}:\n\n{link}",
    confirmation_message: props.team.payment_settings?.confirmation_message || "✅ *Pagamento Confirmado!*\n\nRecebemos o seu pagamento referente a: *{description}*\nValor: *{amount}*\n\nObrigado!",
});

const updatePaymentSettings = () => {
    form.put(route('teams.payment-settings.update', props.team), {
        errorBag: 'updatePaymentSettings',
        preserveScroll: true,
    });
};
</script>

<template>
    <FormSection @submitted="updatePaymentSettings">
        <template #title>
            Configurações de Pagamento
        </template>

        <template #description>
            Configure seus gateways de pagamento para processar transações.
        </template>

        <template #form>
            <div class="col-span-6">
                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-slate-700">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button
                            type="button"
                            @click="activeTab = 'mercadopago'"
                            :class="[
                                activeTab === 'mercadopago'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-all'
                            ]"
                        >
                            Mercado Pago
                        </button>
                        <button
                            type="button"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm text-gray-400 cursor-not-allowed border-transparent"
                            disabled
                        >
                            Stripe (Em breve)
                        </button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="mt-6">
                    <div v-if="activeTab === 'mercadopago'" class="space-y-6">
                        <!-- OAuth Connection Section -->
                        <div class="p-6 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-100 dark:border-slate-800">
                            <div class="flex items-center justify-between flex-wrap gap-4">
                                <div class="flex items-center gap-4">
                                    <div class="size-12 bg-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-blue-500/20 shrink-0">
                                        <svg class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight">Conexão Automática</h3>
                                        <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-0.5">Conecte sua conta de forma rápida e segura.</p>
                                    </div>
                                </div>

                                <div v-if="team.payment_settings?.mercado_pago?.access_token" class="flex items-center gap-2 px-4 py-2 bg-green-50 dark:bg-green-500/10 text-green-600 dark:text-green-400 rounded-xl border border-green-100 dark:border-green-500/20">
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span class="text-[10px] font-black uppercase tracking-widest">Conectado</span>
                                </div>
                                <a v-else :href="route('teams.payment-settings.mercado-pago.connect', team)" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-blue-500/30 flex items-center gap-2">
                                    Conectar Mercado Pago
                                </a>
                            </div>
                        </div>

                        <div class="relative py-2">
                            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                                <div class="w-full border-t border-gray-100 dark:border-slate-800"></div>
                            </div>
                            <div class="relative flex justify-center">
                                <span class="px-3 bg-white dark:bg-slate-900 text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">ou manual</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-4">
                                <InputLabel for="mp_public_key" value="Public Key" />
                                <TextInput
                                    id="mp_public_key"
                                    v-model="form.mercado_pago.public_key"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="APP_USR-..."
                                />
                                <InputError :message="form.errors['mercado_pago.public_key']" class="mt-2" />
                            </div>

                            <div class="col-span-6 sm:col-span-4">
                                <InputLabel for="mp_access_token" value="Access Token" />
                                <TextInput
                                    id="mp_access_token"
                                    v-model="form.mercado_pago.access_token"
                                    type="password"
                                    class="mt-1 block w-full"
                                    placeholder="APP_USR-..."
                                />
                                <InputError :message="form.errors['mercado_pago.access_token']" class="mt-2" />
                            </div>
                        </div>
                        
                        <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl border border-blue-100 dark:border-blue-800">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-300">Como obter as chaves?</h3>
                                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-400">
                                        <p>Você pode encontrar suas credenciais no painel de desenvolvedor do Mercado Pago em "Seas credenciais".</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Message Templates -->
                <div class="mt-8 pt-8 border-t border-gray-100 dark:border-gray-800">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Personalização de Mensagens</h3>
                    
                    <div class="grid grid-cols-6 gap-6">
                        <div class="col-span-6">
                            <InputLabel for="payment_message" value="Mensagem de Envio de Cobrança" />
                            <textarea
                                id="payment_message"
                                v-model="form.payment_message"
                                rows="3"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            ></textarea>
                            <p class="mt-2 text-xs text-gray-500">
                                Variáveis: <code>{description}</code>, <code>{amount}</code>, <code>{link}</code>
                            </p>
                            <InputError :message="form.errors.payment_message" class="mt-2" />
                        </div>

                        <div class="col-span-6">
                            <InputLabel for="confirmation_message" value="Mensagem de Comprovante" />
                            <textarea
                                id="confirmation_message"
                                v-model="form.confirmation_message"
                                rows="3"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            ></textarea>
                            <p class="mt-2 text-xs text-gray-500">
                                Variáveis: <code>{description}</code>, <code>{amount}</code>
                            </p>
                            <InputError :message="form.errors.confirmation_message" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Salvo com sucesso.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Salvar Configurações
            </PrimaryButton>
        </template>
    </FormSection>
</template>
