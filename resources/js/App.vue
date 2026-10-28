<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { api, ApiError, type GameAccess } from './api';
import CommandBar from './components/CommandBar.vue';
import NpcPanel from './components/NpcPanel.vue';
import RuleLedger from './components/RuleLedger.vue';
import SceneArt from './components/SceneArt.vue';
import StoryLog from './components/StoryLog.vue';
import type { Game, GameEvent } from './types';

const STORAGE_KEY = 'thornbridge.game';

const game = ref<Game | null>(null);
const access = ref<GameAccess | null>(null);
const events = ref<GameEvent[]>([]);
const busy = ref(false);
const error = ref<string | null>(null);
const driver = document.querySelector<HTMLMetaElement>('meta[name="npc-driver"]')?.content ?? 'claude';

const rulings = computed(() => events.value.filter((event) => event.type === 'ruling'));

type Tab = 'story' | 'npc' | 'ledger';

/** Below the lg breakpoint the three panels share the screen as tabs, so phones don't scroll the page either. */
const tab = ref<Tab>('story');
const tabs: { key: Tab; label: string }[] = [
    { key: 'story', label: 'Story' },
    { key: 'npc', label: 'Grimble' },
    { key: 'ledger', label: 'Ledger' },
];
const storyLog = ref<InstanceType<typeof StoryLog> | null>(null);
const seenRulings = ref(0);
const unseenRulings = computed(() => (tab.value === 'ledger' ? 0 : rulings.value.length - seenRulings.value));

watch([tab, () => rulings.value.length], () => {
    if (tab.value === 'ledger') {
        seenRulings.value = rulings.value.length;
    }
});

// A hidden panel loses its scroll position, so return to the newest line.
watch(tab, async (current) => {
    if (current === 'story') {
        await nextTick();
        storyLog.value?.scrollToEnd('instant');
    }
});

function remember(saved: GameAccess) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(saved));
    } catch {
        // Storage can be unavailable (private mode); resuming is a convenience.
    }
}

/** The saved game, if storage holds one in the current format (older saves were a bare id with no token). */
function recall(): GameAccess | null {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null');

        return typeof saved?.id === 'string' && typeof saved?.token === 'string' ? { id: saved.id, token: saved.token } : null;
    } catch {
        return null;
    }
}

async function startNewGame() {
    busy.value = true;
    error.value = null;

    try {
        const { token, ...created } = await api.newGame();
        game.value = created;
        access.value = { id: created.id, token };
        events.value = created.events ?? [];
        seenRulings.value = rulings.value.length;
        tab.value = 'story';
        remember(access.value);
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Could not start a game.';
    } finally {
        busy.value = false;
    }
}

async function send(input: string) {
    if (!game.value || !access.value || busy.value) {
        return;
    }

    busy.value = true;
    error.value = null;

    try {
        const result = await api.playTurn(access.value, input);
        events.value.push(...result.events);
        game.value = result.game;
    } catch (e) {
        error.value = e instanceof ApiError ? e.message : 'Something went wrong.';
    } finally {
        busy.value = false;
    }
}

onMounted(async () => {
    const saved = recall();

    if (saved) {
        try {
            const loaded = await api.loadGame(saved);
            game.value = loaded;
            access.value = saved;
            events.value = loaded.events ?? [];
            seenRulings.value = rulings.value.length;
            return;
        } catch {
            // The saved game is gone (fresh database); start over.
        }
    }

    await startNewGame();
});
</script>

<template>
    <div class="mx-auto flex h-dvh max-w-7xl flex-col px-3 py-3 sm:px-6 lg:py-4">
        <header class="mb-3 flex shrink-0 flex-wrap items-end justify-between gap-x-4 gap-y-2">
            <div>
                <h1 class="font-display text-2xl font-semibold tracking-tight text-parchment-100 sm:text-3xl">Thornbridge</h1>
                <p class="mt-1 hidden max-w-2xl text-sm text-parchment-500 sm:block">
                    A text adventure whose troll is voiced by
                    {{ driver === 'scripted' ? 'an offline scripted brain' : 'Claude' }}. The model only
                    <em>proposes</em> what Grimble does. The Laravel server owns the game state and rules on every proposal.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span
                    class="rounded-full border px-2.5 py-1 text-xs font-medium"
                    :class="driver === 'scripted' ? 'border-ember-400/40 text-ember-400' : 'border-moss-400/40 text-moss-400'"
                    :title="driver === 'scripted' ? 'AI_DRIVER=scripted: keyword NPC, no API calls' : 'AI_DRIVER=claude'"
                >
                    NPC brain: {{ driver }}
                </span>
                <button
                    type="button"
                    class="rounded-md border border-ink-600 px-3 py-1.5 text-sm text-parchment-300 transition hover:border-parchment-500 hover:text-parchment-100 disabled:opacity-50"
                    :disabled="busy"
                    @click="startNewGame"
                >
                    New game
                </button>
            </div>
        </header>

        <div v-if="!game" class="py-24 text-center text-parchment-500">
            {{ error ?? 'Setting out…' }}
        </div>

        <template v-else>
            <nav class="mb-3 grid shrink-0 grid-cols-3 gap-1 rounded-lg border border-ink-700 bg-ink-900 p-1 lg:hidden" role="tablist" aria-label="Panels">
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item.key"
                    class="rounded-md px-2 py-1.5 text-sm whitespace-nowrap transition"
                    :class="tab === item.key ? 'bg-ink-700 text-parchment-100' : 'text-parchment-500 hover:text-parchment-300'"
                    @click="tab = item.key"
                >
                    {{ item.label }}
                    <span v-if="item.key === 'npc'" class="ml-0.5 text-xs text-parchment-500 tabular-nums">{{ game.npc.trust }}/{{ game.rules.max_trust }}</span>
                    <span v-if="item.key === 'ledger' && unseenRulings > 0" class="ml-1 rounded-full bg-ember-400/20 px-1.5 text-[11px] text-ember-400 tabular-nums">{{
                        unseenRulings
                    }}</span>
                </button>
            </nav>

            <div class="grid min-h-0 flex-1 grid-rows-[minmax(0,1fr)] gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <main class="flex min-h-0 min-w-0 flex-col gap-3" :class="{ 'max-lg:hidden': tab !== 'story' }">
                    <SceneArt class="max-lg:[@media(max-height:720px)]:hidden" :location="game.location.key" :mood="game.npc.mood" :status="game.status" />
                    <StoryLog ref="storyLog" :events="events" :busy="busy" />
                    <p v-if="error" role="alert" class="rounded-md border border-blood-400/40 bg-blood-400/10 px-3 py-2 text-sm text-blood-400">
                        {{ error }}
                    </p>
                    <div
                        v-if="game.status !== 'playing'"
                        class="rounded-lg border px-4 py-3 text-sm"
                        :class="game.status === 'won' ? 'border-moss-400/40 bg-moss-600/10 text-moss-400' : 'border-blood-400/40 bg-blood-400/10 text-blood-400'"
                    >
                        {{ game.status === 'won' ? 'You crossed Thornbridge.' : 'Night fell before you crossed.' }}
                        <button type="button" class="ml-2 underline underline-offset-2" @click="startNewGame">Play again</button>
                    </div>
                    <CommandBar v-else :game="game" :busy="busy" @send="send" />
                </main>

                <aside class="flex min-h-0 min-w-0 flex-col gap-3 overflow-y-auto" :class="{ 'max-lg:hidden': tab === 'story' }">
                    <NpcPanel :game="game" :class="{ 'max-lg:hidden': tab !== 'npc' }" />
                    <RuleLedger :rulings="rulings" :class="{ 'max-lg:hidden': tab !== 'ledger' }" />
                </aside>
            </div>
        </template>
    </div>
</template>
