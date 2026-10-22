import type { Game, GameEvent } from './types';

export class ApiError extends Error {
    constructor(
        message: string,
        public status: number,
    ) {
        super(message);
    }
}

/** A game id plus the signed token that lets this browser play it. */
export type GameAccess = { id: string; token: string };

async function request<T>(method: string, url: string, body?: unknown, token?: string): Promise<T> {
    const response = await fetch(url, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { 'X-Game-Token': token } : {}),
        },
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
    newGame: () => request<Game & { token: string }>('POST', '/api/games'),
    loadGame: (access: GameAccess) => request<Game>('GET', `/api/games/${access.id}`, undefined, access.token),
    playTurn: (access: GameAccess, input: string) =>
        request<{ events: GameEvent[]; game: Game }>('POST', `/api/games/${access.id}/turns`, { input }, access.token),
};
