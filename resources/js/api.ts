import type { Game, GameEvent } from './types';

export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
    ) {
        super(message);
    }
}

async function request<T>(method: string, url: string, body?: unknown): Promise<T> {
    const response = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    const json = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message =
            response.status === 429
                ? 'Slow down a little: too many turns this minute.'
                : response.status >= 500
                  ? 'Grimble is lost in thought (the AI call failed). Nothing changed. Try again.'
                  : (json.message ?? `Request failed (${response.status}).`);
        throw new ApiError(message, response.status);
    }

    return json as T;
}

export const api = {
    newGame: () => request<Game>('POST', '/api/games'),
    loadGame: (id: string) => request<Game>('GET', `/api/games/${id}`),
    playTurn: (id: string, input: string) =>
        request<{ events: GameEvent[]; game: Game }>('POST', `/api/games/${id}/turns`, { input }),
};
