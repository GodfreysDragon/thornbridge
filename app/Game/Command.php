<?php

namespace App\Game;

/**
 * A parsed player input. Movement, items and money are deterministic verbs
 * the server executes itself; everything else is speech for the NPC.
 */
final readonly class Command
{
    /** Checked in order, so "over the bridge" means crossing, not walking up to it. */
    public const array ALIASES = [
        'far_bank' => ['across', 'over', 'far bank', 'other side', 'cross'],
        'village' => ['village', 'square', 'mossbury', 'town', 'back', 'south', 'west'],
        'forest' => ['forest', 'woods', 'wood', 'whisperwood', 'east', 'trees'],
        'bridge' => ['bridge', 'thornbridge', 'river', 'north', 'gate', 'troll'],
    ];

    public function __construct(
        public string $verb,
        public string $argument = '',
    ) {}

    public static function parse(string $input): self
    {
        $input = trim(preg_replace('/\s+/', ' ', $input));
        $lower = strtolower($input);

        return match (true) {
            in_array($lower, ['look', 'l', 'look around'], true) => new self('look'),
            in_array($lower, ['inventory', 'inv', 'i'], true) => new self('inventory'),
            in_array($lower, ['cross', 'cross bridge', 'cross the bridge'], true) => new self('go', 'far_bank'),
            (bool) preg_match('/^(?:go|walk|move|head|travel)\s+(?:to\s+)?(?:the\s+)?(.+)$/', $lower, $m) => new self('go', self::place($m[1])),
            (bool) preg_match('/^(?:take|get|grab|pick up)\s+(.+)$/', $lower, $m) => new self('take', $m[1]),
            (bool) preg_match('/^(?:give|hand|offer)\s+(.+?)(?:\s+to\s+(?:the\s+)?(?:troll|grimble|him))?$/', $lower, $m) => new self('give', $m[1]),
            (bool) preg_match('/^pay\s+(\d+)(?:\s+gold)?(?:\s+coins?)?$/', $lower, $m) => new self('pay', $m[1]),
            (bool) preg_match('/^pay\b/', $lower) => new self('pay', ''),
            (bool) preg_match('/^(?:say|ask|tell)\s+(.+)$/i', $input, $m) => new self('say', $m[1]),
            default => new self('say', $input),
        };
    }

    private static function place(string $words): string
    {
        foreach (self::ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($words, $alias)) {
                    return $key;
                }
            }
        }

        return $words;
    }
}
