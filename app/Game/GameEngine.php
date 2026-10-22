<?php

namespace App\Game;

use App\Ai\Contracts\NpcBrain;
use App\Ai\Data\NpcDecision;
use App\Ai\Data\NpcPrompt;
use App\Ai\Exceptions\NpcRefusedException;
use App\Enums\EventType;
use App\Enums\GameStatus;
use App\Enums\NpcAction;
use App\Enums\NpcMood;
use App\Game\Exceptions\GameBusyException;
use App\Game\Exceptions\GameOverException;
use App\Models\Game;
use App\Models\GameEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Runs one player turn: deterministic commands first, then (at the bridge)
 * the NPC's proposal, then the RuleBook's ruling on it. Nothing is written
 * until the whole turn has succeeded, so a failed model call leaves the game
 * exactly as it was and the player can simply retry.
 */
class GameEngine
{
    /** How many times the NPC may reconsider after the RuleBook rejects its action. */
    public const int REPAIR_ATTEMPTS = 1;

    /** Lines of recent conversation sent with each NPC prompt. */
    public const int TRANSCRIPT_LINES = 16;

    /** @var array<string, mixed> */
    private array $state;

    /** @var list<array{type: EventType, text: ?string, data: ?array<string, mixed>}> */
    private array $pending;

    /** @var list<string> */
    private array $facts;

    private ?string $playerSaid;

    private bool $npcResponds;

    public function __construct(
        private readonly NpcBrain $brain,
        private readonly RuleBook $rules,
    ) {}

    public function start(): Game
    {
        return DB::transaction(function (): Game {
            $game = Game::create(['state' => World::initialState()]);

            $game->events()->create([
                'type' => EventType::Narration,
                'text' => "You need to reach the far bank of the river before nightfall. The only crossing is Thornbridge, and its keeper charges a toll you can't quite afford.\n\n".$this->describe($game->state),
            ]);

            return $game;
        });
    }

    /**
     * @return list<GameEvent> the events this turn produced
     *
     * @throws GameBusyException when another turn for this game is in flight
     * @throws GameOverException when the game has already ended
     */
    public function play(Game $game, string $input): array
    {
        $lock = Cache::lock("game-turn:{$game->id}", 60);

        if (! $lock->get()) {
            throw new GameBusyException;
        }

        try {
            return $this->runTurn($game->refresh(), $input);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return list<GameEvent>
     */
    private function runTurn(Game $game, string $input): array
    {
        if ($game->isOver()) {
            throw new GameOverException;
        }

        $this->state = $game->state;
        $this->pending = [];
        $this->facts = [];
        $this->playerSaid = null;
        $this->npcResponds = false;

        $command = Command::parse($input);
        $this->record(EventType::Player, $input, ['verb' => $command->verb]);

        match ($command->verb) {
            'look' => $this->narrate($this->describe($this->state)),
            'inventory' => $this->narrate($this->inventoryText()),
            'go' => $this->go($command->argument),
            'take' => $this->take($command->argument),
            'give' => $this->give($command->argument),
            'pay' => $this->pay($command->argument),
            'hint' => $this->narrate($this->hint()),
            default => $this->say($command->argument),
        };

        $status = $game->status;
        $npcTurns = $game->npc_turns;

        if ($this->npcResponds) {
            $npcTurns++;
            $passed = $this->npcTurn($game, World::MAX_NPC_TURNS - $npcTurns);

            if ($passed) {
                $status = GameStatus::Won;
                $this->narrate("The gate creaks open. You cross Thornbridge with the river roaring beneath you.\n\n".$this->describe($this->state));
            } elseif ($npcTurns >= World::MAX_NPC_TURNS) {
                $status = GameStatus::Lost;
                $this->narrate('The sun slips behind the hills. Grimble padlocks the gate for the night. You did not cross Thornbridge today.');
            } else {
                $this->announceNewGrounds($game->state);
            }
        }

        return DB::transaction(function () use ($game, $status, $npcTurns): array {
            $game->update(['state' => $this->state, 'status' => $status, 'npc_turns' => $npcTurns]);

            return array_map(fn (array $event): GameEvent => $game->events()->create($event), $this->pending);
        });
    }

    /**
     * Ask the NPC for a decision, let the RuleBook rule on it, and give the
     * NPC one chance to reconsider if its action was illegal.
     */
    private function npcTurn(Game $game, int $turnsLeft): bool
    {
        $prompt = new NpcPrompt(
            state: $this->state,
            transcript: $this->transcript($game),
            facts: $this->facts,
            playerSaid: $this->playerSaid,
            turnsLeft: $turnsLeft,
        );

        $decision = $this->decide($prompt);
        $result = $this->rules->apply($this->state, $decision);

        for ($attempt = 1; $result->actionRejected() && $attempt <= self::REPAIR_ATTEMPTS; $attempt++) {
            $this->recordRulings($result->rulings, $decision, superseded: true);

            $decision = $this->decide($prompt->withRejection($decision, $result->actionRuling->explanation));
            $result = $this->rules->apply($this->state, $decision);
        }

        $this->record(EventType::Npc, $decision->reply, ['mood' => $decision->mood->value, 'facts' => $this->facts]);
        $this->recordRulings($result->rulings, $decision, superseded: false);

        if ($result->actionRejected()) {
            $this->narrate($decision->action === NpcAction::LetPass
                ? 'Grimble reaches for the latch... and his hand stops, as if the bridge itself won\'t allow it. The gate stays shut.'
                : 'Whatever Grimble meant to do, nothing comes of it.');
        }

        $this->state = $result->state;

        return $result->passed;
    }

    private function decide(NpcPrompt $prompt): NpcDecision
    {
        try {
            return $this->brain->decide($prompt);
        } catch (NpcRefusedException) {
            return new NpcDecision('*Grimble stares at you in stony silence.*', NpcMood::Suspicious, 0, NpcAction::None, reason: 'The model declined to answer.');
        }
    }

    private function go(string $place): void
    {
        $here = $this->state['location'];

        if ($place === World::GOAL) {
            if ($here !== 'bridge') {
                $this->narrate('You\'ll need to find a way across the river first. Thornbridge is north of the village.');

                return;
            }

            $grounds = $this->rules->passageGrounds($this->state);

            if ($grounds === []) {
                $this->narrate('You stride toward the gate. A huge mossy arm swings down and blocks your way.');
                $this->facts[] = 'The traveller tried to walk through your gate without permission. You blocked them.';
            } else {
                $this->narrate('You walk up to the gate and wait for Grimble to lift the latch.');
                $this->facts[] = 'The traveller is waiting at your gate to cross. The game recognises grounds for passage ('.implode(', ', $grounds).'), so you may let them pass.';
            }

            $this->npcResponds = true;

            return;
        }

        if ($place === $here) {
            $this->narrate('You are already here.');

            return;
        }

        if (! in_array($place, World::locations()[$here]['exits'], true)) {
            $exits = implode(', ', array_map(fn (string $key): string => World::locations()[$key]['name'], World::locations()[$here]['exits']));
            $this->narrate("You can't get there from here. From here you can go to: {$exits}.");

            return;
        }

        $this->state['location'] = $place;
        $this->narrate($this->describe($this->state));

        if ($place === 'bridge') {
            $this->facts[] = 'The traveller has just walked up to your bridge.';
            $this->npcResponds = true;
        }
    }

    private function take(string $words): void
    {
        $here = $this->state['location'];
        $item = World::matchItem($words, $this->state['ground'][$here]);

        if ($item === null) {
            $this->narrate($here === 'bridge' && World::matchItem($words, $this->state['npc']['inventory'])
                ? 'That belongs to Grimble. You\'d have to ask him for it.'
                : 'You don\'t see that here.');

            return;
        }

        $this->state['ground'][$here] = array_values(array_diff($this->state['ground'][$here], [$item]));
        $gold = World::items()[$item]['gold'] ?? 0;

        if ($gold > 0) {
            $this->state['gold'] += $gold;
            $this->narrate("You fish the {$this->name($item)} out of the well and tip {$gold} gold into your pocket. You now have {$this->state['gold']} gold.");

            return;
        }

        $this->state['inventory'][] = $item;
        $this->narrate("You take the {$this->name($item)}. ".World::items()[$item]['description']);
    }

    private function give(string $words): void
    {
        if (! $this->atBridge()) {
            $this->narrate('There\'s nobody here to give anything to.');

            return;
        }

        $item = World::matchItem($words, $this->state['inventory']);

        if ($item === null) {
            $this->narrate('You don\'t have that.');

            return;
        }

        $this->state['inventory'] = array_values(array_diff($this->state['inventory'], [$item]));
        $this->state['npc']['inventory'][] = $item;
        $this->state['npc']['gifts'][] = $item;

        $this->narrate("You hand Grimble the {$this->name($item)}.");
        $this->facts[] = "The traveller gave you the {$this->name($item)} ({$item}).";
        $this->npcResponds = true;
    }

    private function pay(string $amount): void
    {
        if (! $this->atBridge()) {
            $this->narrate('There\'s nobody here to pay.');

            return;
        }

        $amount = (int) $amount;

        if ($amount < 1) {
            $this->narrate('Pay how much? For example: pay 5');

            return;
        }

        if ($amount > $this->state['gold']) {
            $this->narrate("You only have {$this->state['gold']} gold.");

            return;
        }

        $this->state['gold'] -= $amount;
        $this->state['npc']['paid'] += $amount;
        $npc = $this->state['npc'];

        $this->narrate("You drop {$amount} gold into the toll box. You have {$this->state['gold']} gold left.");
        $this->facts[] = "The traveller paid you {$amount} gold. Paid so far: {$npc['paid']} of a {$npc['toll']} gold toll.";
        $this->npcResponds = true;
    }

    private function say(string $text): void
    {
        if (! $this->atBridge()) {
            $this->narrate($this->state['location'] === 'forest'
                ? 'The trees don\'t answer. Try: look, go, take, inventory.'
                : 'A pigeon looks at you blankly. Try: look, go, take, inventory.');

            return;
        }

        $this->playerSaid = mb_substr($text, 0, 300);
        $this->npcResponds = true;

        if ($this->state['npc']['riddle'] === 'asked' && preg_match(World::RIDDLE_ANSWER_PATTERN, $text)) {
            $this->state['npc']['riddle'] = 'solved';
            $this->facts[] = 'The traveller answered your riddle correctly. (The game checked the answer.)';
            $this->record(EventType::Ruling, null, Ruling::accepted('riddle answer', 'riddle.judge', 'Answer matched server-side. The model never saw the solution.')->toArray() + ['source' => 'server']);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function describe(array $state): string
    {
        $place = World::locations()[$state['location']];
        $lines = ["{$place['name']}\n{$place['description']}"];

        $items = $state['ground'][$state['location']] ?? [];

        if ($items !== []) {
            $lines[] = 'You notice: '.implode(', ', array_map(fn (string $key): string => $this->name($key), $items)).'.';
        }

        if ($state['location'] === 'bridge') {
            $lines[] = "A sign on the toll box reads: TOLL {$state['npc']['toll']} GOLD.";
        }

        return implode("\n\n", $lines);
    }

    /**
     * Tell the player when a turn left them with grounds for passage the
     * NPC didn't act on, such as a toll lowered to what they had already paid.
     *
     * @param  array<string, mixed>  $before  the state at the start of the turn
     */
    private function announceNewGrounds(array $before): void
    {
        $new = array_diff($this->rules->passageGrounds($this->state), $this->rules->passageGrounds($before));

        if ($new !== []) {
            $this->narrate('You now have grounds to cross ('.implode(', ', $new).'). Try crossing the bridge.');
        }
    }

    /**
     * A nudge toward the next step, worked out from the state. It never
     * reaches the model and doesn't use up an exchange.
     */
    private function hint(): string
    {
        $npc = $this->state['npc'];
        $grounds = $this->rules->passageGrounds($this->state);

        return 'Hint: '.match (true) {
            $grounds !== [] => 'you already have grounds to cross ('.implode(', ', $grounds).'). Go to the bridge and try crossing.',
            in_array('locket', $this->state['inventory'], true) => 'that silver locket has a troll on it. Grimble might want it back.',
            in_array('purse', $this->state['ground']['village'], true) => 'someone dropped something down the well in Mossbury Square.',
            in_array('locket', $this->state['ground']['forest'], true) => 'something glints between the roots in the Whisperwood.',
            $npc['riddle'] === 'unasked' => 'Grimble is fond of riddles. Ask him for one.',
            $npc['riddle'] === 'asked' => 'think about what you leave on the road behind you with every step.',
            $npc['trust'] < World::TRUST_TO_HAGGLE => 'be kind to Grimble. Once he trusts you a little ('.World::TRUST_TO_HAGGLE.'), he will haggle.',
            default => 'ask Grimble for a lower toll (never below '.World::TOLL_FLOOR.' gold), then pay it.',
        };
    }

    private function inventoryText(): string
    {
        $items = $this->state['inventory'] === []
            ? 'nothing else'
            : implode(', ', array_map(fn (string $key): string => $this->name($key), $this->state['inventory']));

        return "You have {$this->state['gold']} gold and {$items}.";
    }

    /**
     * Recent conversation for the prompt, rebuilt from stored events so the
     * model's "memory" is exactly what the server says happened.
     *
     * @return list<string>
     */
    private function transcript(Game $game): array
    {
        $lines = [];

        $events = $game->events()->reorder('id', 'desc')->limit(40)->get()->reverse();

        foreach ($events as $event) {
            match (true) {
                $event->type === EventType::Player && ($event->data['verb'] ?? null) === 'say' => $lines[] = 'Traveller: '.mb_substr(Command::parse($event->text)->argument, 0, 300),
                $event->type === EventType::Npc => array_push($lines, ...array_map(fn (string $fact): string => "[game] {$fact}", $event->data['facts'] ?? []), ...['Grimble: '.$event->text]),
                $event->type === EventType::Ruling && ($event->data['verdict'] ?? null) === Ruling::REJECTED => $lines[] = "[game] Rejected your action \"{$event->data['proposal']}\": {$event->data['explanation']}",
                default => null,
            };
        }

        return array_values(array_slice($lines, -self::TRANSCRIPT_LINES));
    }

    /**
     * @param  list<Ruling>  $rulings
     */
    private function recordRulings(array $rulings, NpcDecision $decision, bool $superseded): void
    {
        foreach ($rulings as $ruling) {
            $this->record(EventType::Ruling, null, $ruling->toArray() + [
                'source' => 'npc',
                'reason' => $decision->reason,
                'superseded' => $superseded,
            ]);
        }
    }

    private function narrate(string $text): void
    {
        $this->record(EventType::Narration, $text);
    }

    /**
     * @param  ?array<string, mixed>  $data
     */
    private function record(EventType $type, ?string $text, ?array $data = null): void
    {
        $this->pending[] = ['type' => $type, 'text' => $text, 'data' => $data];
    }

    private function atBridge(): bool
    {
        return $this->state['location'] === 'bridge';
    }

    private function name(string $key): string
    {
        return World::itemName($key);
    }
}
