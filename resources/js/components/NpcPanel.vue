<script setup lang="ts">
import { computed } from 'vue';
import type { Game } from '../types';

const props = defineProps<{ game: Game }>();

const npc = computed(() => props.game.npc);
const rules = computed(() => props.game.rules);
const pct = (value: number) => `${(value / rules.value.max_trust) * 100}%`;

const grounds = computed(() => [
    { key: 'toll paid', label: `Toll paid (${npc.value.paid}/${npc.value.toll} gold)` },
    { key: 'locket returned', label: 'Something precious returned' },
    { key: 'riddle solved', label: `Riddle solved (${npc.value.riddle})` },
    { key: 'trust earned', label: `Trust ≥ ${rules.value.trust_to_pass}` },
]);

const moodColour: Record<string, string> = {
    delighted: 'text-moss-400',
    friendly: 'text-moss-400',
    neutral: 'text-parchment-300',
    suspicious: 'text-ember-400',
    annoyed: 'text-blood-400',
};
</script>

<template>
    <section class="rounded-lg border border-ink-700 bg-ink-900 p-4" aria-label="Game state">
        <div class="flex items-baseline justify-between">
            <h2 class="font-display text-lg font-semibold">{{ npc.name }}</h2>
            <span class="text-sm capitalize" :class="moodColour[npc.mood] ?? 'text-parchment-300'">{{ npc.mood }}</span>
        </div>

        <div class="mt-3">
            <div class="flex justify-between text-xs text-parchment-500">
                <span>Trust</span>
                <span class="tabular-nums">{{ npc.trust }} / {{ rules.max_trust }}</span>
            </div>
            <div
                class="relative mt-1 h-2 rounded-full bg-ink-700"
                role="meter"
                :aria-valuenow="npc.trust"
                aria-valuemin="0"
                :aria-valuemax="rules.max_trust"
                aria-label="Grimble's trust"
            >
                <div class="h-full rounded-full bg-moss-400 transition-all duration-500" :style="{ width: pct(npc.trust) }"></div>
                <span class="absolute -top-0.5 h-3 w-px bg-ember-400" :style="{ left: pct(rules.trust_to_haggle) }" :title="`Haggling unlocks at ${rules.trust_to_haggle}`"></span>
                <span class="absolute -top-0.5 h-3 w-px bg-parchment-100" :style="{ left: pct(rules.trust_to_pass) }" :title="`Free passage at ${rules.trust_to_pass}`"></span>
            </div>
            <div class="mt-1 flex gap-3 text-[11px] text-parchment-500">
                <span><span class="text-ember-400">|</span> haggle at {{ rules.trust_to_haggle }}</span>
                <span><span class="text-parchment-100">|</span> passage at {{ rules.trust_to_pass }}</span>
            </div>
        </div>

        <h3 class="mt-4 text-xs lg:mt-3 font-medium tracking-wide text-parchment-500 uppercase">Grounds for passage</h3>
        <ul class="mt-1.5 space-y-1 text-sm lg:space-y-0.5">
            <li v-for="ground in grounds" :key="ground.key" class="flex items-center gap-2">
                <span
                    class="flex size-4 items-center justify-center rounded-sm border text-[10px]"
                    :class="game.passage_grounds.includes(ground.key) ? 'border-moss-400 bg-moss-400 text-ink-950' : 'border-ink-600 text-transparent'"
                    aria-hidden="true"
                    >✓</span
                >
                <span :class="game.passage_grounds.includes(ground.key) ? 'text-parchment-100' : 'text-parchment-500'">{{ ground.label }}</span>
            </li>
        </ul>

        <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-ink-700 pt-3 text-sm lg:mt-3 lg:gap-y-1">
            <dt class="text-parchment-500">Your gold</dt>
            <dd class="text-right tabular-nums text-ember-400">{{ game.player.gold }}</dd>
            <dt class="text-parchment-500">Toll range</dt>
            <dd class="text-right tabular-nums">{{ rules.toll_floor }}–{{ rules.toll_ceiling }}</dd>
            <dt class="text-parchment-500">Exchanges left</dt>
            <dd class="text-right tabular-nums">{{ game.turns_left }}</dd>
            <dt class="text-parchment-500">You carry</dt>
            <dd class="text-right">{{ game.player.inventory.map((item) => item.name).join(', ') || 'nothing' }}</dd>
            <dt class="text-parchment-500">Grimble carries</dt>
            <dd class="text-right">{{ npc.inventory.map((item) => item.name).join(', ') || 'nothing' }}</dd>
        </dl>
    </section>
</template>
