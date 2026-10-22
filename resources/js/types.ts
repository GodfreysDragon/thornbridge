export type ItemRef = { key: string; name: string };

export type EventType = 'narration' | 'player' | 'npc' | 'ruling';

export type Verdict = 'accepted' | 'adjusted' | 'rejected';

export type RulingData = {
    proposal: string;
    verdict: Verdict;
    rule: string;
    explanation: string;
    source: 'npc' | 'server';
    reason?: string;
    superseded?: boolean;
};

export type GameEvent = {
    id: number;
    type: EventType;
    text: string | null;
    data: (Partial<RulingData> & { mood?: string; verb?: string; facts?: string[]; prompt?: string }) | null;
    created_at: string;
};

export type Game = {
    id: string;
    status: 'playing' | 'won' | 'lost';
    turns_left: number;
    location: { key: string; name: string; exits: ItemRef[]; items: ItemRef[]; has_npc: boolean };
    player: { gold: number; inventory: ItemRef[] };
    npc: {
        name: string;
        mood: string;
        trust: number;
        toll: number;
        paid: number;
        riddle: 'unasked' | 'asked' | 'solved';
        inventory: ItemRef[];
        gifts: ItemRef[];
    };
    passage_grounds: string[];
    rules: {
        max_trust: number;
        trust_to_haggle: number;
        trust_to_pass: number;
        toll_floor: number;
        toll_ceiling: number;
        max_npc_turns: number;
    };
    events?: GameEvent[];
};
