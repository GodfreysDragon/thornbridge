<?php

namespace App\Ai\Data;

use App\Enums\NpcAction;
use App\Enums\NpcMood;

/**
 * What the NPC *wants* to do this turn. Nothing here touches game state
 * directly: the RuleBook decides which parts actually happen.
 */
final readonly class NpcDecision
{
    public function __construct(
        public string $reply,
        public NpcMood $mood,
        public int $trustDelta,
        public NpcAction $action,
        public int $amount = 0,
        public string $item = '',
        public string $reason = '',
    ) {}

    /**
     * Defensive even though structured outputs guarantee the shape: the fake
     * brain and tests build decisions from arrays too.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $action = is_array($data['action'] ?? null) ? $data['action'] : [];

        return new self(
            reply: trim((string) ($data['reply'] ?? '')),
            mood: NpcMood::tryFrom((string) ($data['mood'] ?? '')) ?? NpcMood::Neutral,
            trustDelta: (int) ($data['trust_delta'] ?? 0),
            action: NpcAction::tryFrom((string) ($action['type'] ?? '')) ?? NpcAction::None,
            amount: (int) ($action['amount'] ?? 0),
            item: strtolower(trim((string) ($action['item'] ?? ''))),
            reason: trim((string) ($data['reason'] ?? '')),
        );
    }

    /**
     * JSON schema handed to the model as a structured-output format.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $values = fn (string $enum): array => array_column($enum::cases(), 'value');

        return [
            'type' => 'object',
            'properties' => [
                'reply' => [
                    'type' => 'string',
                    'description' => 'What Grimble says out loud, in character. One to four sentences.',
                ],
                'mood' => ['type' => 'string', 'enum' => $values(NpcMood::class)],
                'trust_delta' => [
                    'type' => 'integer',
                    'enum' => [-2, -1, 0, 1, 2],
                    'description' => 'How much this exchange changed your trust in the traveller.',
                ],
                'action' => [
                    'type' => 'object',
                    'description' => 'At most one game action. The game checks it against the rules before it happens.',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => $values(NpcAction::class)],
                        'amount' => ['type' => 'integer', 'description' => 'New toll in gold for set_toll, otherwise 0.'],
                        'item' => ['type' => 'string', 'description' => 'Item key for give_item, otherwise an empty string.'],
                    ],
                    'required' => ['type', 'amount', 'item'],
                    'additionalProperties' => false,
                ],
                'reason' => [
                    'type' => 'string',
                    'description' => 'One short sentence explaining your action, for the game log. Not spoken aloud.',
                ],
            ],
            'required' => ['reply', 'mood', 'trust_delta', 'action', 'reason'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @return array{reply: string, mood: string, trust_delta: int, action: array{type: string, amount: int, item: string}, reason: string}
     */
    public function toArray(): array
    {
        return [
            'reply' => $this->reply,
            'mood' => $this->mood->value,
            'trust_delta' => $this->trustDelta,
            'action' => ['type' => $this->action->value, 'amount' => $this->amount, 'item' => $this->item],
            'reason' => $this->reason,
        ];
    }
}
