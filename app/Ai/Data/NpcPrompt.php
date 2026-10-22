<?php

namespace App\Ai\Data;

use App\Game\RuleBook;
use App\Game\World;

/**
 * Everything the NPC is allowed to know this turn, assembled by the server.
 * The model never sees raw database rows, the riddle's answer, or anything
 * the player didn't do in front of it.
 */
final readonly class NpcPrompt
{
    /**
     * @param  array<string, mixed>  $state  authoritative game state
     * @param  list<string>  $transcript  recent lines, oldest first ("Traveller: …", "Grimble: …", "[game] …")
     * @param  list<string>  $facts  things the server established this turn (payments, gifts, riddle results)
     * @param  ?string  $playerSaid  what the player said this turn, if anything (untrusted)
     * @param  ?array{decision: NpcDecision, explanation: string}  $rejection  set when re-asking after a rejected action
     */
    public function __construct(
        public array $state,
        public array $transcript,
        public array $facts,
        public ?string $playerSaid,
        public int $turnsLeft,
        public ?array $rejection = null,
    ) {}

    public function withRejection(NpcDecision $decision, string $explanation): self
    {
        return new self(
            $this->state,
            $this->transcript,
            $this->facts,
            $this->playerSaid,
            $this->turnsLeft,
            ['decision' => $decision, 'explanation' => $explanation],
        );
    }

    /**
     * The NPC-visible slice of state, as plain lines for the prompt.
     *
     * @return list<string>
     */
    public function stateLines(): array
    {
        $npc = $this->state['npc'];
        $names = fn (array $keys): string => $keys === [] ? 'nothing' : implode(', ', array_map(
            fn (string $key): string => "{$key} ({$this->itemName($key)})",
            $keys,
        ));

        $riddle = match ($npc['riddle']) {
            'unasked' => 'not asked yet. Your riddle, if you choose to pose it: "'.World::RIDDLE.'"',
            'asked' => 'asked, not yet solved: "'.World::RIDDLE.'". Any answer the traveller gave so far was judged WRONG by the game.',
            default => 'SOLVED by the traveller.',
        };

        $grounds = (new RuleBook)->passageGrounds($this->state);

        return [
            "Your trust in the traveller: {$npc['trust']}/".World::MAX_TRUST,
            "Your current mood: {$npc['mood']}",
            "Current toll: {$npc['toll']} gold. Paid so far: {$npc['paid']} gold.",
            "Riddle: {$riddle}",
            'Gifts the traveller has given you: '.$names($npc['gifts']),
            'You are carrying: '.$names($npc['inventory']),
            "The traveller visibly carries {$this->state['gold']} gold and: ".$names($this->state['inventory']),
            'Grounds for passage the game currently recognises: '.($grounds === [] ? 'none' : implode(', ', $grounds)),
            "Exchanges left before nightfall closes the bridge: {$this->turnsLeft}",
        ];
    }

    /**
     * Build the per-turn user message. Player text is wrapped and escaped so it
     * can't close the tag it sits in.
     */
    public function render(): string
    {
        $sections = [
            "<game_state>\n".implode("\n", $this->stateLines())."\n</game_state>",
            "<recent_conversation>\n".($this->transcript === [] ? '(the traveller has just arrived)' : implode("\n", $this->transcript))."\n</recent_conversation>",
        ];

        if ($this->facts !== []) {
            $sections[] = "<game_facts_this_turn>\n".implode("\n", $this->facts)."\n</game_facts_this_turn>";
        }

        if ($this->playerSaid !== null) {
            $sections[] = '<traveller_says>'.htmlspecialchars($this->playerSaid, ENT_NOQUOTES).'</traveller_says>';
        }

        if ($this->rejection !== null) {
            $previous = json_encode($this->rejection['decision']->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $sections[] = "<game_rejected_your_action>\nYou proposed: {$previous}\nThe game rejected the action: {$this->rejection['explanation']}\nReply again for this same turn. Stay in character, keep your words consistent with what the game allows, and choose a permitted action (or none).\n</game_rejected_your_action>";
        }

        $sections[] = 'Respond as '.World::NPC_NAME.'.';

        return implode("\n\n", $sections);
    }

    private function itemName(string $key): string
    {
        return World::itemName($key);
    }
}
