<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import MascotEro from '@/Components/MascotEro.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const currentStep = ref(1);
const totalSteps = 4;
const isTalking = ref(false);
const displayedMessage = ref('');
let typeTimer = null;

const mascotConfig = computed(() => {
    switch (currentStep.value) {
        case 1: return { mood: 'happy', action: 'waving', msg: 'Olá! Eu sou o Ero, seu assistente. Vou te guiar no cadastro. Primeiro, qual é o seu nome?' };
        case 2: return { mood: 'happy', action: 'idle', msg: `Prazer, ${form.name.split(' ')[0] || ''}! Agora me diz, qual o seu melhor e-mail?` };
        case 3: return { mood: 'thinking', action: 'idle', msg: 'Segurança é essencial! Crie uma senha bem forte para proteger sua conta.' };
        case 4: return { mood: 'excited', action: 'waving', msg: 'Estamos quase lá! Aceite os termos e finalize seu cadastro. Vai ser incrível!' };
        default: return { mood: 'happy', action: 'idle', msg: '' };
    }
});

const typeMessage = (text) => {
    clearTimeout(typeTimer);
    isTalking.value = true;
    displayedMessage.value = '';
    let i = 0;
    const type = () => {
        if (i < text.length) {
            displayedMessage.value += text[i];
            i++;
            typeTimer = setTimeout(type, 25);
        } else {
            isTalking.value = false;
        }
    };
    type();
};

watch(currentStep, () => {
    typeMessage(mascotConfig.value.msg);
}, { immediate: true });

const canAdvance = computed(() => {
    if (currentStep.value === 1) return form.name.length > 0;
    if (currentStep.value === 2) return form.email.length > 0;
    if (currentStep.value === 3) return form.password.length >= 1;
    return true;
});

const nextStep = () => {
    if (currentStep.value < totalSteps && canAdvance.value) currentStep.value++;
};

const prevStep = () => {
    if (currentStep.value > 1) currentStep.value--;
};

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Registrar" />

    <div class="register-page">
        <!-- Ambient background -->
        <div class="ambient-bg">
            <div class="orb orb-1"></div>
            <div class="orb orb-2"></div>
            <div class="orb orb-3"></div>
        </div>

        <div class="register-container">
            <!-- Left: Mascot -->
            <div class="mascot-section">
                <div class="mascot-area">
                    <MascotEro
                        :mood="mascotConfig.mood"
                        :action="mascotConfig.action"
                        :isTalking="isTalking"
                    />
                </div>
                <div class="speech-bubble">
                    <div class="speech-arrow"></div>
                    <p class="speech-text">
                        {{ displayedMessage }}<span v-if="isTalking" class="cursor-blink">|</span>
                    </p>
                </div>
            </div>

            <!-- Right: Form -->
            <div class="form-section">
                <div class="form-card">
                    <!-- Header -->
                    <div class="form-header">
                        <AuthenticationCardLogo class="form-logo" />
                        <h1 class="form-title">Criar Conta</h1>
                        <p class="form-subtitle">Passo {{ currentStep }} de {{ totalSteps }}</p>

                        <!-- Progress bar -->
                        <div class="progress-bar">
                            <div v-for="i in totalSteps" :key="i" class="progress-segment">
                                <div class="progress-fill" :class="{ active: i <= currentStep }"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Steps -->
                    <form @submit.prevent="submit" class="form-body">
                        <transition mode="out-in"
                            enter-active-class="step-enter-active"
                            enter-from-class="step-enter-from"
                            leave-active-class="step-leave-active"
                            leave-to-class="step-leave-to"
                        >
                            <!-- Step 1: Name -->
                            <div v-if="currentStep === 1" key="s1" class="step-content">
                                <label class="field-label">NOME COMPLETO</label>
                                <div class="input-wrapper">
                                    <TextInput
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        class="field-input"
                                        placeholder="Como podemos te chamar?"
                                        required autofocus autocomplete="name"
                                    />
                                </div>
                                <InputError class="field-error" :message="form.errors.name" />
                            </div>

                            <!-- Step 2: Email -->
                            <div v-else-if="currentStep === 2" key="s2" class="step-content">
                                <label class="field-label">E-MAIL</label>
                                <div class="input-wrapper">
                                    <TextInput
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        class="field-input"
                                        placeholder="seu@email.com"
                                        required autocomplete="username"
                                    />
                                </div>
                                <InputError class="field-error" :message="form.errors.email" />
                            </div>

                            <!-- Step 3: Password -->
                            <div v-else-if="currentStep === 3" key="s3" class="step-content">
                                <label class="field-label">SENHA</label>
                                <div class="input-wrapper">
                                    <TextInput
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        class="field-input"
                                        placeholder="Mínimo 8 caracteres"
                                        required autocomplete="new-password"
                                    />
                                </div>
                                <InputError class="field-error" :message="form.errors.password" />
                                <label class="field-label" style="margin-top: 1.25rem;">CONFIRMAR SENHA</label>
                                <div class="input-wrapper">
                                    <TextInput
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        class="field-input"
                                        placeholder="Repita sua senha"
                                        required autocomplete="new-password"
                                    />
                                </div>
                                <InputError class="field-error" :message="form.errors.password_confirmation" />
                            </div>

                            <!-- Step 4: Terms & Submit -->
                            <div v-else-if="currentStep === 4" key="s4" class="step-content">
                                <div v-if="$page.props.jetstream.hasTermsAndPrivacyPolicyFeature" class="terms-box">
                                    <Checkbox id="terms" v-model:checked="form.terms" name="terms" required />
                                    <span class="terms-text">
                                        Eu aceito os
                                        <a :href="route('terms.show')" target="_blank" class="terms-link">Termos</a>
                                        e a
                                        <a :href="route('policy.show')" target="_blank" class="terms-link">Privacidade</a>.
                                    </span>
                                </div>
                                <InputError class="field-error" :message="form.errors.terms" />

                                <button type="submit" class="submit-btn" :disabled="form.processing">
                                    <span v-if="!form.processing">FINALIZAR CADASTRO</span>
                                    <span v-else>PROCESSANDO...</span>
                                </button>
                            </div>
                        </transition>

                        <!-- Navigation -->
                        <div class="form-nav">
                            <button v-if="currentStep > 1" type="button" @click="prevStep" class="nav-back">
                                ← Voltar
                            </button>
                            <div v-else></div>

                            <button
                                v-if="currentStep < totalSteps"
                                type="button"
                                @click="nextStep"
                                :disabled="!canAdvance"
                                class="nav-next"
                            >
                                Próximo →
                            </button>
                        </div>

                        <div class="form-footer">
                            <Link :href="route('login')" class="login-link">Já tem uma conta? Entrar</Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ===== PAGE ===== */
.register-page {
    min-height: 100vh;
    background: #0a0f1e;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    position: relative;
    overflow: hidden;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}

/* ===== AMBIENT BACKGROUND ===== */
.ambient-bg {
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 0;
}
.orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
}
.orb-1 {
    width: 500px; height: 500px;
    background: rgba(59, 130, 246, 0.12);
    top: -15%; left: -10%;
    animation: orb-drift 12s ease-in-out infinite;
}
.orb-2 {
    width: 400px; height: 400px;
    background: rgba(139, 92, 246, 0.1);
    bottom: -10%; right: -5%;
    animation: orb-drift 15s ease-in-out infinite reverse;
}
.orb-3 {
    width: 300px; height: 300px;
    background: rgba(34, 197, 94, 0.06);
    top: 40%; left: 50%;
    animation: orb-drift 18s ease-in-out infinite 3s;
}
@keyframes orb-drift {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -20px) scale(1.05); }
    66% { transform: translate(-20px, 15px) scale(0.95); }
}

/* ===== CONTAINER ===== */
.register-container {
    display: flex;
    align-items: center;
    gap: 4rem;
    max-width: 1100px;
    width: 100%;
    position: relative;
    z-index: 1;
}

/* ===== MASCOT SECTION ===== */
.mascot-section {
    flex: 0 0 280px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
}
.mascot-area {
    width: 240px;
    height: 280px;
    filter: drop-shadow(0 15px 40px rgba(59, 130, 246, 0.2));
}

/* ===== SPEECH BUBBLE ===== */
.speech-bubble {
    position: relative;
    background: rgba(30, 41, 59, 0.85);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 1.25rem;
    padding: 1.25rem 1.5rem;
    max-width: 300px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}
.speech-arrow {
    position: absolute;
    top: -8px;
    left: 50%;
    transform: translateX(-50%) rotate(45deg);
    width: 16px;
    height: 16px;
    background: rgba(30, 41, 59, 0.85);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    border-left: 1px solid rgba(255, 255, 255, 0.08);
}
.speech-text {
    color: #e2e8f0;
    font-size: 0.9rem;
    line-height: 1.6;
    margin: 0;
}
.cursor-blink {
    display: inline-block;
    color: #3b82f6;
    font-weight: bold;
    animation: blink-cursor 0.6s step-end infinite;
}
@keyframes blink-cursor {
    0%, 100% { opacity: 1; }
    50% { opacity: 0; }
}

/* ===== FORM SECTION ===== */
.form-section {
    flex: 1;
    min-width: 0;
}
.form-card {
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(20px) saturate(1.5);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 2rem;
    padding: 2.5rem;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
}

/* Header */
.form-header {
    text-align: center;
    margin-bottom: 2rem;
}
.form-logo {
    height: 2.5rem;
    width: auto;
    margin: 0 auto 1rem;
    display: block;
}
.form-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: white;
    margin: 0 0 0.25rem;
    letter-spacing: -0.02em;
}
.form-subtitle {
    font-size: 0.85rem;
    color: #64748b;
    margin: 0 0 1.5rem;
    font-weight: 500;
}

/* Progress */
.progress-bar {
    display: flex;
    gap: 0.5rem;
}
.progress-segment {
    flex: 1;
    height: 4px;
    background: rgba(255, 255, 255, 0.06);
    border-radius: 2px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #3b82f6, #6366f1);
    border-radius: 2px;
    transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}
.progress-fill.active {
    width: 100%;
}

/* Form body */
.form-body {
    min-height: 200px;
}

/* Fields */
.field-label {
    display: block;
    font-size: 0.7rem;
    color: #94a3b8;
    font-weight: 700;
    letter-spacing: 0.1em;
    margin-bottom: 0.5rem;
}
.input-wrapper {
    position: relative;
}
.field-input {
    width: 100%;
    padding: 0.95rem 1.25rem !important;
    font-size: 1rem !important;
    background: rgba(0, 0, 0, 0.35) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 1rem !important;
    color: white !important;
    transition: all 0.3s ease !important;
    outline: none !important;
    box-sizing: border-box;
}
.field-input::placeholder {
    color: #475569 !important;
}
.field-input:focus {
    border-color: rgba(59, 130, 246, 0.5) !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), 0 0 20px rgba(59, 130, 246, 0.08) !important;
}
.field-error {
    margin-top: 0.5rem;
    font-size: 0.8rem;
}

/* Step transitions */
.step-content {
    padding: 0.5rem 0;
}
.step-enter-active { transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
.step-leave-active { transition: all 0.2s ease; }
.step-enter-from { opacity: 0; transform: translateX(30px); }
.step-leave-to { opacity: 0; transform: translateX(-30px); }

/* Terms */
.terms-box {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1.25rem;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 1rem;
    margin-bottom: 1.5rem;
}
.terms-text {
    color: #94a3b8;
    font-size: 0.85rem;
    line-height: 1.5;
}
.terms-link {
    color: #60a5fa;
    text-decoration: underline;
    text-underline-offset: 2px;
    font-weight: 600;
}
.terms-link:hover {
    color: #93bbfc;
}

/* Submit */
.submit-btn {
    width: 100%;
    padding: 1.1rem;
    font-size: 1rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    color: white;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    border: none;
    border-radius: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 30px rgba(59, 130, 246, 0.35);
}
.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(59, 130, 246, 0.45);
}
.submit-btn:active {
    transform: translateY(0);
}
.submit-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none;
}

/* Navigation */
.form-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.04);
}
.nav-back {
    padding: 0.7rem 1.25rem;
    background: none;
    border: none;
    color: #64748b;
    font-weight: 700;
    font-size: 0.85rem;
    cursor: pointer;
    transition: color 0.2s;
    border-radius: 0.75rem;
}
.nav-back:hover { color: #e2e8f0; }

.nav-next {
    padding: 0.7rem 2rem;
    background: white;
    color: #0f172a;
    border: none;
    border-radius: 0.75rem;
    font-weight: 800;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.25s ease;
    letter-spacing: 0.02em;
}
.nav-next:hover {
    background: #3b82f6;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
}
.nav-next:disabled {
    opacity: 0.3;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Footer */
.form-footer {
    text-align: center;
    margin-top: 1.5rem;
}
.login-link {
    color: #64748b;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.2s;
}
.login-link:hover { color: #60a5fa; }

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
    .register-container {
        flex-direction: column;
        gap: 2rem;
    }
    .mascot-section {
        flex: none;
    }
    .mascot-area {
        width: 180px;
        height: 210px;
    }
    .speech-bubble {
        max-width: 100%;
    }
    .form-card {
        padding: 2rem 1.5rem;
    }
}

@media (max-width: 500px) {
    .register-page {
        padding: 1rem 0.75rem;
    }
    .mascot-area {
        width: 140px;
        height: 170px;
    }
    .form-card {
        padding: 1.5rem 1.25rem;
        border-radius: 1.5rem;
    }
    .form-title {
        font-size: 1.4rem;
    }
}
</style>
