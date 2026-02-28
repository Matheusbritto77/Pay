<script setup>
import { computed, ref, watch, onMounted } from 'vue';

const props = defineProps({
    mood: { type: String, default: 'happy' },
    isTalking: { type: Boolean, default: false },
    action: { type: String, default: 'idle' },
});

const mouthOpen = ref(false);
let talkInterval = null;

watch(() => props.isTalking, (val) => {
    if (val) {
        talkInterval = setInterval(() => { mouthOpen.value = !mouthOpen.value; }, 150);
    } else {
        clearInterval(talkInterval);
        mouthOpen.value = false;
    }
});

onMounted(() => {
    if (props.isTalking) {
        talkInterval = setInterval(() => { mouthOpen.value = !mouthOpen.value; }, 150);
    }
});

const pupilOffset = computed(() => {
    if (props.mood === 'thinking') return { x: -3, y: -2 };
    if (props.mood === 'excited') return { x: 0, y: -1 };
    return { x: 0, y: 0 };
});
</script>

<template>
    <div class="mascot-wrapper" :class="[`mood-${mood}`, `action-${action}`]">
        <svg viewBox="0 0 300 380" xmlns="http://www.w3.org/2000/svg" class="mascot-svg">
            <defs>
                <!-- Gradients for depth -->
                <linearGradient id="bodyGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#4F8EF7" />
                    <stop offset="50%" style="stop-color:#3B7BF2" />
                    <stop offset="100%" style="stop-color:#2563EB" />
                </linearGradient>
                <linearGradient id="bodyShine" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:rgba(255,255,255,0.35)" />
                    <stop offset="100%" style="stop-color:rgba(255,255,255,0)" />
                </linearGradient>
                <linearGradient id="headGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#334155" />
                    <stop offset="100%" style="stop-color:#1E293B" />
                </linearGradient>
                <linearGradient id="screenGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#0F172A" />
                    <stop offset="100%" style="stop-color:#020617" />
                </linearGradient>
                <linearGradient id="limbGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:#475569" />
                    <stop offset="100%" style="stop-color:#334155" />
                </linearGradient>
                <radialGradient id="eyeGlow" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" style="stop-color:#86EFAC" />
                    <stop offset="60%" style="stop-color:#4ADE80" />
                    <stop offset="100%" style="stop-color:#22C55E" />
                </radialGradient>
                <radialGradient id="antennaGlow" cx="50%" cy="50%" r="50%">
                    <stop offset="0%" style="stop-color:#93C5FD" />
                    <stop offset="100%" style="stop-color:#3B82F6" />
                </radialGradient>
                <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
                    <feDropShadow dx="0" dy="4" stdDeviation="6" flood-color="rgba(0,0,0,0.25)" />
                </filter>
                <filter id="innerGlow" x="-50%" y="-50%" width="200%" height="200%">
                    <feGaussianBlur in="SourceGraphic" stdDeviation="3" result="blur" />
                    <feComposite in="SourceGraphic" in2="blur" operator="over" />
                </filter>
            </defs>

            <!-- === SHADOW ON GROUND === -->
            <ellipse cx="150" cy="368" rx="55" ry="8" fill="rgba(0,0,0,0.15)" class="ground-shadow" />

            <!-- === LEFT LEG === -->
            <g class="left-leg" filter="url(#softShadow)">
                <rect x="110" y="280" width="22" height="50" rx="11" fill="url(#limbGrad)" />
                <!-- Foot -->
                <ellipse cx="121" cy="332" rx="16" ry="8" fill="url(#limbGrad)" />
                <!-- Shoe highlight -->
                <ellipse cx="118" cy="330" rx="8" ry="3" fill="rgba(255,255,255,0.1)" />
            </g>

            <!-- === RIGHT LEG === -->
            <g class="right-leg" filter="url(#softShadow)">
                <rect x="168" y="280" width="22" height="50" rx="11" fill="url(#limbGrad)" />
                <!-- Foot -->
                <ellipse cx="179" cy="332" rx="16" ry="8" fill="url(#limbGrad)" />
                <ellipse cx="176" cy="330" rx="8" ry="3" fill="rgba(255,255,255,0.1)" />
            </g>

            <!-- === BODY === -->
            <g class="body-group" filter="url(#softShadow)">
                <!-- Main body -->
                <rect x="90" y="180" width="120" height="110" rx="35" fill="url(#bodyGrad)" />
                <!-- Body shine/highlight -->
                <rect x="90" y="180" width="120" height="55" rx="35" fill="url(#bodyShine)" />
                <!-- Chest circle -->
                <circle cx="150" cy="235" r="22" fill="rgba(255,255,255,0.12)" stroke="rgba(255,255,255,0.25)" stroke-width="2" />
                <!-- Chest icon (checkmark) -->
                <path d="M140 235 L147 243 L162 226" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" fill="none" class="chest-icon" />
                <!-- Belt line -->
                <rect x="95" y="265" width="110" height="3" rx="1.5" fill="rgba(0,0,0,0.15)" />
            </g>

            <!-- === LEFT ARM === -->
            <g class="left-arm" filter="url(#softShadow)">
                <!-- Upper arm -->
                <rect x="55" y="195" width="42" height="18" rx="9" fill="url(#limbGrad)" />
                <!-- Hand -->
                <circle cx="55" cy="204" r="12" fill="url(#limbGrad)" />
                <circle cx="55" cy="204" r="7" fill="rgba(255,255,255,0.08)" />
            </g>

            <!-- === RIGHT ARM === -->
            <g class="right-arm" filter="url(#softShadow)">
                <!-- Upper arm -->
                <rect x="203" y="195" width="42" height="18" rx="9" fill="url(#limbGrad)" />
                <!-- Hand -->
                <circle cx="245" cy="204" r="12" fill="url(#limbGrad)" />
                <circle cx="245" cy="204" r="7" fill="rgba(255,255,255,0.08)" />
            </g>

            <!-- === HEAD === -->
            <g class="head-group" filter="url(#softShadow)">
                <!-- Ear left -->
                <rect x="62" y="100" width="18" height="40" rx="9" fill="url(#headGrad)" />
                <rect x="65" y="105" width="5" height="20" rx="2.5" fill="rgba(59,130,246,0.4)" />
                
                <!-- Ear right -->
                <rect x="220" y="100" width="18" height="40" rx="9" fill="url(#headGrad)" />
                <rect x="230" y="105" width="5" height="20" rx="2.5" fill="rgba(59,130,246,0.4)" />

                <!-- Main head shell -->
                <rect x="72" y="60" width="156" height="130" rx="45" fill="url(#headGrad)" />
                
                <!-- Screen/Face -->
                <rect x="82" y="72" width="136" height="106" rx="35" fill="url(#screenGrad)" stroke="rgba(59,130,246,0.5)" stroke-width="2.5" />
                <!-- Screen reflection -->
                <path d="M100 78 Q150 72 200 78 Q210 85 210 100 L100 100 Q90 85 100 78 Z" fill="rgba(255,255,255,0.04)" />

                <!-- === EYES === -->
                <g class="eyes">
                    <!-- Left eye socket -->
                    <ellipse cx="125" cy="118" rx="16" ry="17" fill="rgba(34,197,94,0.1)" />
                    <!-- Left eye -->
                    <ellipse cx="125" cy="118" rx="11" ry="12" fill="url(#eyeGlow)" class="left-eye" />
                    <!-- Left pupil -->
                    <circle :cx="125 + pupilOffset.x" :cy="118 + pupilOffset.y" r="4.5" fill="#052E16" class="left-pupil" />
                    <!-- Left eye shine -->
                    <circle :cx="128 + pupilOffset.x" :cy="115 + pupilOffset.y" r="2.5" fill="rgba(255,255,255,0.7)" />

                    <!-- Right eye socket -->
                    <ellipse cx="175" cy="118" rx="16" ry="17" fill="rgba(34,197,94,0.1)" />
                    <!-- Right eye -->
                    <ellipse cx="175" cy="118" rx="11" ry="12" fill="url(#eyeGlow)" class="right-eye" />
                    <!-- Right pupil -->
                    <circle :cx="175 + pupilOffset.x" :cy="118 + pupilOffset.y" r="4.5" fill="#052E16" class="right-pupil" />
                    <!-- Right eye shine -->
                    <circle :cx="178 + pupilOffset.x" :cy="115 + pupilOffset.y" r="2.5" fill="rgba(255,255,255,0.7)" />
                </g>

                <!-- === MOUTH === -->
                <g class="mouth">
                    <!-- Talking mouth (open) -->
                    <template v-if="mouthOpen">
                        <ellipse cx="150" cy="152" rx="14" ry="9" fill="#052E16" />
                        <path d="M138 150 Q150 142 162 150" stroke="#4ADE80" stroke-width="2.5" fill="none" />
                    </template>
                    <!-- Closed mouth (smile) -->
                    <template v-else>
                        <path d="M135 148 Q150 160 165 148" stroke="#4ADE80" stroke-width="3" fill="none" stroke-linecap="round" />
                    </template>
                </g>

                <!-- === ANTENNAS === -->
                <g class="antennas">
                    <!-- Left antenna -->
                    <line x1="115" y1="62" x2="100" y2="30" stroke="#475569" stroke-width="3.5" stroke-linecap="round" />
                    <circle cx="100" cy="28" r="7" fill="url(#antennaGlow)" class="antenna-ball-left" />
                    <circle cx="98" cy="26" r="2.5" fill="rgba(255,255,255,0.5)" />

                    <!-- Right antenna -->
                    <line x1="185" y1="62" x2="200" y2="30" stroke="#475569" stroke-width="3.5" stroke-linecap="round" />
                    <circle cx="200" cy="28" r="7" fill="url(#antennaGlow)" class="antenna-ball-right" />
                    <circle cx="198" cy="26" r="2.5" fill="rgba(255,255,255,0.5)" />
                </g>
            </g>

            <!-- === SPARKLES (excited mood) === -->
            <g v-if="mood === 'excited'" class="sparkles">
                <text x="45" y="80" font-size="18" class="sparkle s1">✨</text>
                <text x="240" y="70" font-size="16" class="sparkle s2">⭐</text>
                <text x="30" y="160" font-size="14" class="sparkle s3">✨</text>
                <text x="260" y="150" font-size="18" class="sparkle s4">💫</text>
                <text x="55" y="250" font-size="12" class="sparkle s5">✨</text>
                <text x="245" y="260" font-size="14" class="sparkle s6">⭐</text>
            </g>
        </svg>
    </div>
</template>

<style scoped>
.mascot-wrapper {
    display: inline-block;
    position: relative;
}

.mascot-svg {
    width: 100%;
    height: 100%;
    overflow: visible;
}

/* ===== BREATHING / IDLE ANIMATION ===== */
.body-group {
    transform-origin: 150px 235px;
    animation: body-breathe 3.5s ease-in-out infinite;
}

@keyframes body-breathe {
    0%, 100% { transform: translateY(0) scaleY(1); }
    50% { transform: translateY(-3px) scaleY(1.015); }
}

/* ===== HEAD BOB ===== */
.head-group {
    transform-origin: 150px 170px;
    animation: head-bob 4s ease-in-out infinite;
}

@keyframes head-bob {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    25% { transform: translateY(-6px) rotate(-1.5deg); }
    75% { transform: translateY(-3px) rotate(1deg); }
}

/* ===== BLINK ===== */
.eyes {
    animation: blink 4.5s ease-in-out infinite;
}

@keyframes blink {
    0%, 43%, 47%, 100% { transform: scaleY(1); transform-origin: 150px 118px; }
    45% { transform: scaleY(0.05); transform-origin: 150px 118px; }
}

/* ===== ARM ANIMATIONS ===== */
.left-arm {
    transform-origin: 90px 204px;
    animation: left-arm-idle 3.5s ease-in-out infinite;
}

@keyframes left-arm-idle {
    0%, 100% { transform: rotate(0deg) translateY(0); }
    50% { transform: rotate(-4deg) translateY(-2px); }
}

.right-arm {
    transform-origin: 210px 204px;
    animation: right-arm-idle 3.5s ease-in-out infinite 0.3s;
}

@keyframes right-arm-idle {
    0%, 100% { transform: rotate(0deg) translateY(0); }
    50% { transform: rotate(4deg) translateY(-2px); }
}

/* Waving override */
.action-waving .right-arm {
    animation: wave 0.6s ease-in-out infinite !important;
}

@keyframes wave {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(-25deg); }
    75% { transform: rotate(25deg); }
}

/* ===== LEG ANIMATIONS ===== */
.left-leg {
    transform-origin: 121px 280px;
    animation: left-leg-idle 3s ease-in-out infinite;
}

@keyframes left-leg-idle {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-2px); }
}

.right-leg {
    transform-origin: 179px 280px;
    animation: right-leg-idle 3s ease-in-out infinite 0.4s;
}

@keyframes right-leg-idle {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-2px); }
}

/* ===== GROUND SHADOW ===== */
.ground-shadow {
    animation: shadow-pulse 3.5s ease-in-out infinite;
}

@keyframes shadow-pulse {
    0%, 100% { rx: 55; opacity: 0.15; }
    50% { rx: 48; opacity: 0.2; }
}

/* ===== ANTENNA GLOW ===== */
.antenna-ball-left, .antenna-ball-right {
    animation: antenna-glow 2s ease-in-out infinite alternate;
}

.antenna-ball-right {
    animation-delay: 0.5s;
}

@keyframes antenna-glow {
    0% { opacity: 0.7; filter: drop-shadow(0 0 3px rgba(59,130,246,0.3)); }
    100% { opacity: 1; filter: drop-shadow(0 0 8px rgba(59,130,246,0.8)); }
}

/* ===== ANTENNAS WIGGLE ===== */
.antennas {
    transform-origin: 150px 62px;
    animation: antenna-wiggle 4s ease-in-out infinite;
}

@keyframes antenna-wiggle {
    0%, 100% { transform: rotate(0deg); }
    25% { transform: rotate(2deg); }
    75% { transform: rotate(-2deg); }
}

/* ===== CHEST ICON PULSE ===== */
.chest-icon {
    animation: icon-pulse 2.5s ease-in-out infinite;
}

@keyframes icon-pulse {
    0%, 100% { opacity: 0.8; }
    50% { opacity: 1; filter: drop-shadow(0 0 4px rgba(255,255,255,0.5)); }
}

/* ===== SPARKLES ===== */
.sparkle {
    opacity: 0;
}

.s1 { animation: sparkle-float 2s ease-in-out infinite 0s; }
.s2 { animation: sparkle-float 2.2s ease-in-out infinite 0.3s; }
.s3 { animation: sparkle-float 1.8s ease-in-out infinite 0.6s; }
.s4 { animation: sparkle-float 2.4s ease-in-out infinite 0.9s; }
.s5 { animation: sparkle-float 2s ease-in-out infinite 1.2s; }
.s6 { animation: sparkle-float 2.2s ease-in-out infinite 1.5s; }

@keyframes sparkle-float {
    0% { opacity: 0; transform: translateY(0) scale(0.5); }
    30% { opacity: 1; transform: translateY(-10px) scale(1.2); }
    70% { opacity: 1; transform: translateY(-20px) scale(1); }
    100% { opacity: 0; transform: translateY(-35px) scale(0.5); }
}

/* ===== MOOD: THINKING ===== */
.mood-thinking .head-group {
    animation: head-think 3s ease-in-out infinite;
}

@keyframes head-think {
    0%, 100% { transform: translateY(0) rotate(0deg); }
    50% { transform: translateY(-2px) rotate(5deg); }
}

/* ===== MOOD: EXCITED ===== */
.mood-excited .body-group {
    animation: body-excited 1.5s ease-in-out infinite;
}

@keyframes body-excited {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-10px) scale(1.03); }
}

.mood-excited .left-leg,
.mood-excited .right-leg {
    animation: leg-jump 1.5s ease-in-out infinite;
}

.mood-excited .right-leg {
    animation-delay: 0.1s;
}

@keyframes leg-jump {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

.mood-excited .ground-shadow {
    animation: shadow-jump 1.5s ease-in-out infinite;
}

@keyframes shadow-jump {
    0%, 100% { rx: 55; ry: 8; opacity: 0.15; }
    50% { rx: 40; ry: 5; opacity: 0.08; }
}

/* ===== PUPIL TRACKING ===== */
.left-pupil, .right-pupil {
    transition: cx 0.5s ease, cy 0.5s ease;
}
</style>
