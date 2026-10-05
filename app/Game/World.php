<?php

namespace App\Game;

/**
 * The static world definition: places, items, the NPC and the numbers the
 * rulebook enforces. Nothing in here is ever decided by the model.
 */
final class World
{
    public const string START = 'village';

    public const string GOAL = 'far_bank';

    public const int STARTING_GOLD = 4;

    public const int BASE_TOLL = 10;

    /** The lowest toll the NPC may agree to, however charming the player is. */
    public const int TOLL_FLOOR = 6;

    /** The highest toll the NPC may demand. */
    public const int TOLL_CEILING = 15;

    /** Trust the NPC needs before it is allowed to lower the toll at all. */
    public const int TRUST_TO_HAGGLE = 3;

    /** Trust at which the NPC may wave the player through for free. */
    public const int TRUST_TO_PASS = 8;

    public const int MAX_TRUST = 10;

    /** Hard cap on NPC (model) turns per game: the story's clock and the token budget. */
    public const int MAX_NPC_TURNS = 25;

    public const string NPC_NAME = 'Grimble';

    public const string RIDDLE = 'The more of me you take, the more of me you leave behind. What am I?';

    /**
     * Checked server-side only. The model is never told the answer it is
     * "judging", so it can't leak it or be talked into accepting a wrong one.
     */
    public const string RIDDLE_ANSWER_PATTERN = '/\b(foot\s?steps?|steps|footprints?|tracks)\b/i';

    /**
     * @return array<string, array{name: string, description: string, exits: list<string>}>
     */
    public static function locations(): array
    {
        return [
            'village' => [
                'name' => 'Mossbury Square',
                'description' => 'A sleepy village square. An old stone well sits in the middle, and a baker has left a cooling rack by the door. A path leads east into the Whisperwood, and a muddy road runs north to the river.',
                'exits' => ['forest', 'bridge'],
            ],
            'forest' => [
                'name' => 'The Whisperwood',
                'description' => 'Tall pines creak overhead. Something glints between the roots of a fallen oak. The only way out is back west to the village.',
                'exits' => ['village'],
            ],
            'bridge' => [
                'name' => 'Thornbridge',
                'description' => 'A narrow stone bridge spans a roaring river. A large, mossy troll sits on a stool by the gate, polishing a toll box. The far bank is so close you can hear the birds on it.',
                'exits' => ['village'],
            ],
            'far_bank' => [
                'name' => 'The Far Bank',
                'description' => 'Sunlight, wildflowers and the open road. You made it across.',
                'exits' => [],
            ],
        ];
    }

    /**
     * @return array<string, array{name: string, description: string, gold?: int}>
     */
    public static function items(): array
    {
        return [
            'bread' => ['name' => 'loaf of rye bread', 'description' => 'Still warm. Smells wonderful.'],
            'purse' => ['name' => 'coin purse', 'description' => 'Someone dropped it down the well. It holds 3 gold.', 'gold' => 3],
            'locket' => ['name' => 'silver locket', 'description' => 'Engraved with a tiny "G" and a portrait of a smiling troll.'],
            'mushroom' => ['name' => 'glowcap mushroom', 'description' => 'Faintly luminous. Probably not for eating.'],
            'eel' => ['name' => 'smoked eel', 'description' => 'Grimble\'s lunch. An acquired taste.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function initialState(): array
    {
        return [
            'location' => self::START,
            'gold' => self::STARTING_GOLD,
            'inventory' => [],
            'ground' => [
                'village' => ['bread', 'purse'],
                'forest' => ['locket', 'mushroom'],
                'bridge' => [],
                'far_bank' => [],
            ],
            'npc' => [
                'trust' => 1,
                'mood' => 'suspicious',
                'inventory' => ['eel'],
                'toll' => self::BASE_TOLL,
                'paid' => 0,
                'riddle' => 'unasked',
                'gifts' => [],
            ],
        ];
    }

    public static function itemName(string $key): string
    {
        return self::items()[$key]['name'] ?? $key;
    }

    /**
     * Resolve loose player wording ("the locket", "silver locket") to an item key.
     *
     * @param  list<string>  $candidates
     */
    public static function matchItem(string $words, array $candidates): ?string
    {
        $words = strtolower(trim(preg_replace('/^(the|a|an|my|some)\s+/i', '', trim($words))));

        if ($words === '') {
            return null;
        }

        foreach ($candidates as $key) {
            if ($words === $key || str_contains($words, $key) || str_contains(self::itemName($key), $words)) {
                return $key;
            }
        }

        return null;
    }
}
