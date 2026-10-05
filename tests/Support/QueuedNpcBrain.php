<?php

namespace Tests\Support;

use App\Ai\Contracts\NpcBrain;
use App\Ai\Data\NpcDecision;
use App\Ai\Data\NpcPrompt;
use App\Enums\NpcAction;
use App\Enums\NpcMood;
use Closure;
use RuntimeException;

/**
 * Plays back queued decisions (or throwables) and records every prompt, so
 * tests can script a misbehaving model and inspect what it was told.
 */
class QueuedNpcBrain implements NpcBrain
{
    /** @var list<NpcPrompt> */
    public array $prompts = [];

    /**
     * @param  list<NpcDecision|\Throwable|Closure(NpcPrompt): NpcDecision>  $queue
     */
    public function __construct(private array $queue = []) {}

    public static function decision(NpcAction $action = NpcAction::None, string $reply = 'Hrm.', int $trust = 0, int $amount = 0, string $item = ''): NpcDecision
    {
        return new NpcDecision($reply, NpcMood::Neutral, $trust, $action, $amount, $item, 'scripted by test');
    }

    public function decide(NpcPrompt $prompt): NpcDecision
    {
        $this->prompts[] = $prompt;
        $next = array_shift($this->queue) ?? self::decision();

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next instanceof Closure ? $next($prompt) : $next;
    }

    public function failNextWith(string $message = 'API down'): void
    {
        array_unshift($this->queue, new RuntimeException($message));
    }
}
