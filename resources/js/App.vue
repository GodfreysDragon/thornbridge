<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
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
            return;
        } catch {
            // The saved game is gone (fresh database); start over.
        }
    }

    await startNewGame();
});
</script>

<template>
    <div class="mx-auto flex max-w-7xl flex-col px-4 py-6 sm:px-6 lg:h-dvh lg:py-4">
        <header class="mb-6 flex shrink-0 flex-wrap items-end justify-between gap-4 lg:mb-3">
            <div>
                <h1 class="font-display text-3xl font-semibold tracking-tight text-parchment-100 sm:text-4xl lg:text-3xl">Thornbridge</h1>
                <p class="mt-1 max-w-2xl text-sm text-parchment-500">
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

        <div v-else class="grid gap-6 lg:min-h-0 lg:flex-1 lg:grid-cols-[minmax(0,1fr)_22rem] lg:grid-rows-[minmax(0,1fr)] lg:gap-4">
            <main class="flex min-w-0 flex-col gap-4 lg:min-h-0 lg:gap-3">
                <SceneArt :location="game.location.key" :mood="game.npc.mood" :status="game.status" />
                <StoryLog :events="events" :busy="busy" />
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

            <aside class="flex min-w-0 flex-col gap-4 lg:min-h-0 lg:gap-3 lg:overflow-y-auto">
                <NpcPanel :game="game" />
                <RuleLedger :rulings="rulings" />
            </aside>
        </div>
    </div>
</template>
