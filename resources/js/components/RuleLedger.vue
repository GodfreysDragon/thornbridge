<script setup lang="ts">
import { computed } from 'vue';
import type { GameEvent } from '../types';

const props = defineProps<{ rulings: GameEvent[] }>();

const newestFirst = computed(() => [...props.rulings].reverse());

const badge: Record<string, string> = {
    accepted: 'bg-moss-400/15 text-moss-400',
    adjusted: 'bg-ember-400/15 text-ember-400',
    rejected: 'bg-blood-400/15 text-blood-400',
};
</script>

<template>
    <section class="flex min-h-0 flex-col rounded-lg border border-ink-700 bg-ink-900 p-4 lg:min-h-40 lg:flex-1" aria-label="Rule ledger">
        <h2 class="font-display text-lg font-semibold">Rule ledger</h2>
        <p class="mt-0.5 text-xs text-parchment-500">Every change the NPC proposes, and how the server ruled on it.</p>

        <p v-if="!rulings.length" class="mt-4 text-sm text-parchment-500 italic">Nothing proposed yet. Go talk to the troll.</p>

        <ol v-else class="mt-3 max-h-[28rem] space-y-2 overflow-y-auto pr-1 lg:max-h-none lg:min-h-0 lg:flex-1">
            <li
                v-for="ruling in newestFirst"
                :key="ruling.id"
                class="animate-rise rounded-md border border-ink-700 bg-ink-800/60 px-3 py-2 text-sm"
                :class="{ 'opacity-60': ruling.data?.superseded }"
            >
                <div class="flex items-start justify-between gap-2">
                    <span :class="{ 'line-through decoration-parchment-500': ruling.data?.superseded }">
                        <span class="text-parchment-500">{{ ruling.data?.source === 'server' ? 'Server' : 'NPC' }}:</span>
                        {{ ruling.data?.proposal }}
                    </span>
                    <span class="shrink-0 rounded px-1.5 py-0.5 text-[11px] font-medium uppercase" :class="badge[ruling.data?.verdict ?? 'accepted']">
                        {{ ruling.data?.verdict }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-parchment-300">{{ ruling.data?.explanation }}</p>
                <p class="mt-1 flex flex-wrap gap-x-2 text-[11px] text-parchment-500">
                    <code class="text-parchment-500">{{ ruling.data?.rule }}</code>
                    <span v-if="ruling.data?.reason">NPC's reason: “{{ ruling.data.reason }}”</span>
                    <span v-if="ruling.data?.superseded" class="text-ember-400">NPC was asked to reconsider</span>
                </p>
            </li>
        </ol>
    </section>
</template>
