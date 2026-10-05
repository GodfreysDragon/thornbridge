<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ location: string; mood: string; status: string }>();

const sky = computed(() =>
    props.status === 'lost'
        ? ['#0b1020', '#1b2240']
        : props.location === 'far_bank'
          ? ['#3b6d8f', '#d9b97a']
          : props.location === 'forest'
            ? ['#16241c', '#253a2b']
            : ['#22343b', '#4c5f55'],
);

/** Mouth path for the troll, by mood: a smile, a flat line, or a frown. */
const mouth = computed(() => {
    switch (props.mood) {
        case 'delighted':
            return 'M-12 6 Q0 18 12 6';
        case 'friendly':
            return 'M-10 7 Q0 13 10 7';
        case 'annoyed':
            return 'M-10 11 Q0 3 10 11';
        case 'suspicious':
            return 'M-9 9 L9 7';
        default:
            return 'M-10 8 L10 8';
    }
});

const brow = computed(() => (props.mood === 'annoyed' || props.mood === 'suspicious' ? 4 : 0));
</script>

<template>
    <figure class="overflow-hidden rounded-lg border border-ink-700" aria-hidden="true">
        <svg viewBox="0 0 800 200" class="block h-auto w-full" preserveAspectRatio="xMidYMid slice">
            <defs>
                <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0" :stop-color="sky[0]" />
                    <stop offset="1" :stop-color="sky[1]" />
                </linearGradient>
            </defs>
            <rect width="800" height="200" fill="url(#sky)" />
            <circle v-if="status === 'lost'" cx="680" cy="45" r="18" fill="#e9e4cf" opacity="0.8" />
            <circle v-else-if="location === 'far_bank'" cx="640" cy="60" r="34" fill="#f6d27a" />

            <!-- Village: cottages and a well -->
            <g v-if="location === 'village'">
                <path d="M0 160 Q200 140 400 155 T800 150 V200 H0Z" fill="#2f3d33" />
                <g v-for="(x, i) in [90, 230, 560, 680]" :key="i" :transform="`translate(${x} ${118 - (i % 2) * 6})`">
                    <rect x="0" y="12" width="70" height="44" fill="#6b5a45" />
                    <path d="M-8 14 L35 -16 L78 14Z" fill="#3e2f26" />
                    <rect x="27" y="32" width="16" height="24" fill="#2a211b" />
                    <rect x="8" y="22" width="12" height="10" fill="#f0c66a" opacity="0.85" />
                </g>
                <g transform="translate(400 128)">
                    <ellipse cx="0" cy="30" rx="34" ry="8" fill="#555c55" />
                    <rect x="-34" y="0" width="68" height="30" fill="#7b8079" />
                    <path d="M-40 -32 H40 L0 -52Z" fill="#4a3a2e" />
                    <rect x="-30" y="-32" width="4" height="32" fill="#4a3a2e" />
                    <rect x="26" y="-32" width="4" height="32" fill="#4a3a2e" />
                </g>
            </g>

            <!-- Forest: layered pines and a glint -->
            <g v-else-if="location === 'forest'">
                <g v-for="(x, i) in [20, 110, 190, 300, 420, 520, 610, 720]" :key="i" :transform="`translate(${x} ${60 + (i % 3) * 12})`" :fill="i % 2 ? '#1f3a29' : '#2b4c35'">
                    <path d="M30 0 L60 60 H0Z" />
                    <path d="M30 30 L68 100 H-8Z" />
                    <rect x="25" y="100" width="10" height="40" fill="#3b2c22" />
                </g>
                <path d="M0 170 Q400 150 800 170 V200 H0Z" fill="#1a2a1f" />
                <circle cx="470" cy="168" r="3" fill="#f2f2e6"><animate attributeName="opacity" values="0.2;1;0.2" dur="2.4s" repeatCount="indefinite" /></circle>
            </g>

            <!-- Bridge: river, stone arch, gate and troll -->
            <g v-else-if="location === 'bridge'">
                <rect y="140" width="800" height="60" fill="#2c5d78" />
                <path d="M0 150 Q100 145 200 150 T400 150 T600 150 T800 150" stroke="#6aaed6" stroke-width="2" fill="none" opacity="0.6">
                    <animate attributeName="d" dur="3s" repeatCount="indefinite" values="M0 150 Q100 145 200 150 T400 150 T600 150 T800 150;M0 150 Q100 155 200 150 T400 150 T600 150 T800 150;M0 150 Q100 145 200 150 T400 150 T600 150 T800 150" />
                </path>
                <path d="M0 130 H130 V200 H0Z" fill="#3a4a3e" />
                <path d="M660 130 H800 V200 H660Z" fill="#4f6b45" />
                <path d="M120 112 H680 V128 Q400 70 120 128Z" fill="#7d8178" />
                <rect x="120" y="104" width="560" height="10" fill="#8e9289" />
                <g transform="translate(470 54)">
                    <rect x="0" y="0" width="8" height="54" fill="#4a3a2e" />
                    <rect x="52" y="0" width="8" height="54" fill="#4a3a2e" />
                    <rect v-for="i in 5" :key="i" :x="i * 10 - 2" y="6" width="3" height="46" fill="#5c4a3a" :opacity="status === 'won' ? 0.15 : 1" />
                    <rect x="0" y="0" width="60" height="6" fill="#4a3a2e" />
                </g>
                <g transform="translate(380 62)">
                    <ellipse cx="0" cy="34" rx="34" ry="30" fill="#5d7a4a" />
                    <circle cx="0" cy="0" r="22" fill="#6d8c57" />
                    <circle cx="-17" cy="-14" r="6" fill="#6d8c57" />
                    <circle cx="17" cy="-14" r="6" fill="#6d8c57" />
                    <circle cx="-8" cy="-3" r="2.5" fill="#141a18" />
                    <circle cx="8" cy="-3" r="2.5" fill="#141a18" />
                    <path :d="`M-13 ${-10 + brow} L-4 ${-9 - brow / 2}`" stroke="#2c3a24" stroke-width="2.5" stroke-linecap="round" />
                    <path :d="`M13 ${-10 + brow} L4 ${-9 - brow / 2}`" stroke="#2c3a24" stroke-width="2.5" stroke-linecap="round" />
                    <path :d="mouth" stroke="#2c3a24" stroke-width="2.5" fill="none" stroke-linecap="round" />
                    <rect x="34" y="34" width="18" height="14" rx="2" fill="#8a6b3a" />
                </g>
            </g>

            <!-- Far bank: meadow and flowers -->
            <g v-else>
                <path d="M0 150 Q300 120 800 145 V200 H0Z" fill="#5d8a3e" />
                <circle v-for="i in 18" :key="i" :cx="(i * 47) % 800" :cy="160 + (i % 4) * 9" r="3" :fill="['#f0a64a', '#efe8d8', '#e46a5e'][i % 3]" />
            </g>
        </svg>
    </figure>
</template>
