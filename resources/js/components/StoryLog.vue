<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import type { GameEvent } from '../types';

const props = defineProps<{ events: GameEvent[]; busy: boolean }>();

const log = ref<HTMLElement | null>(null);
const story = computed(() => props.events.filter((event) => event.type !== 'ruling'));

/** Each NPC line's rulings from the same turn, for the compact verdicts shown when the ledger is off screen. */
const verdictsByNpcLine = computed(() => {
    const byLine = new Map<number, GameEvent[]>();
    let rulings: GameEvent[] = [];
    let npcLine: number | null = null;

    const flush = () => {
        if (npcLine !== null) {
            byLine.set(npcLine, rulings);
        }
    };

    for (const event of props.events) {
        if (event.type === 'player') {
            flush();
            rulings = [];
            npcLine = null;
        } else if (event.type === 'ruling') {
            rulings.push(event);
        } else if (event.type === 'npc') {
            npcLine = event.id;
        }
    }

    flush();

    return byLine;
});

const verdictStyle: Record<string, string> = {
    accepted: 'border-moss-400/30 text-moss-400',
    adjusted: 'border-ember-400/30 text-ember-400',
    rejected: 'border-blood-400/30 text-blood-400',
};

watch(
    () => [props.events.length, props.busy],
    async () => {
        await nextTick();
        log.value?.scrollTo({ top: log.value.scrollHeight, behavior: 'smooth' });
    },
);
</script>

<template>
    <section
        ref="log"
        aria-live="polite"
        aria-label="Story"
        class="h-[26rem] overflow-y-auto rounded-lg border border-ink-700 bg-ink-900 p-4 sm:h-[30rem] sm:p-5"
    >
        <ol class="space-y-4">
            <li v-for="event in story" :key="event.id" class="animate-rise">
                <p v-if="event.type === 'player'" class="text-right">
                    <span class="inline-block max-w-[85%] rounded-lg rounded-br-sm bg-river-400/15 px-3 py-2 text-left text-sm text-river-400">
                        {{ event.text }}
                    </span>
                </p>
                <div v-else-if="event.type === 'npc'" class="flex gap-3">
                    <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full bg-moss-600/30 font-display text-sm text-moss-400" aria-hidden="true">G</span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium tracking-wide text-moss-400 uppercase">
                            Grimble <span class="font-normal text-parchment-500 normal-case">· {{ event.data?.mood }}</span>
                        </p>
                        <p class="mt-0.5 text-[15px] leading-relaxed text-parchment-100">{{ event.text }}</p>
                        <ul v-if="verdictsByNpcLine.get(event.id)?.length" class="mt-1.5 flex flex-wrap gap-1.5 lg:hidden" aria-label="How the server ruled">
                            <li
                                v-for="ruling in verdictsByNpcLine.get(event.id)"
                                :key="ruling.id"
                                class="rounded border px-1.5 py-0.5 text-[11px]"
                                :class="[verdictStyle[ruling.data?.verdict ?? 'accepted'], { 'line-through opacity-60': ruling.data?.superseded }]"
                            >
                                {{ ruling.data?.verdict }}: {{ ruling.data?.proposal }}
                            </li>
                        </ul>
                        <details v-if="event.data?.prompt" class="mt-1.5 text-xs text-parchment-500">
                            <summary class="cursor-pointer select-none hover:text-parchment-300">What the server told Grimble</summary>
                            <pre class="mt-1.5 max-h-64 overflow-auto rounded-md border border-ink-700 bg-ink-950 p-2 font-mono text-[11px] leading-relaxed whitespace-pre-wrap text-parchment-300">{{ event.data.prompt }}</pre>
                        </details>
                    </div>
                </div>
                <p v-else class="text-[15px] leading-relaxed whitespace-pre-line text-parchment-300">{{ event.text }}</p>
            </li>
            <li v-if="busy" class="flex items-center gap-2 text-sm text-parchment-500">
                <span class="size-1.5 animate-pulse rounded-full bg-moss-400"></span>
                The world is deciding…
            </li>
        </ol>
    </section>
</template>
