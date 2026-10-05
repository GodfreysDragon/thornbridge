<?php

namespace App\Game;

use App\Ai\Data\NpcDecision;
use App\Enums\NpcAction;

/**
 * The server's authority over the NPC. The model proposes; this class
 * disposes. It is pure (state in, state out) so every rule is unit-testable
 * without a model, a database or a request.
 */
final class RuleBook
{
    /**
     * Apply a decision to the state, accepting, clamping or rejecting each part.
     *
     * Every permission check reads the state as it was *before* this decision,
     * so the NPC can't grant itself the trust it needs in the same breath.
     *
     * @param  array<string, mixed>  $state
     */
    public function apply(array $state, NpcDecision $decision): RuleBookResult
    {
        $before = $state;
        $rulings = [];

        $state['npc']['mood'] = $decision->mood->value;

        [$state, $rulings[]] = $this->applyTrust($state, $decision->trustDelta);

        $actionRuling = match ($decision->action) {
            NpcAction::None => null,
            NpcAction::SetToll => $this->setToll($before, $decision->amount),
            NpcAction::PoseRiddle => $this->poseRiddle($before),
            NpcAction::GiveItem => $this->giveItem($before, $decision->item),
            NpcAction::LetPass => $this->letPass($before),
        };

        $passed = false;

        if ($actionRuling !== null) {
            $rulings[] = $actionRuling;

            if (! $actionRuling->isRejected()) {
                [$state, $passed] = $this->perform($state, $decision);
            }
        }

        return new RuleBookResult(
            state: $state,
            rulings: array_values(array_filter($rulings)),
            actionRuling: $actionRuling,
            passed: $passed,
        );
    }

    /**
     * Which of the passage conditions the player currently meets.
     *
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    public function passageGrounds(array $state): array
    {
        $npc = $state['npc'];

        return array_keys(array_filter([
            'toll paid' => $npc['paid'] >= $npc['toll'],
            'locket returned' => in_array('locket', $npc['gifts'], true),
            'riddle solved' => $npc['riddle'] === 'solved',
            'trust earned' => $npc['trust'] >= World::TRUST_TO_PASS,
        ]));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array{0: array<string, mixed>, 1: ?Ruling}
     */
    private function applyTrust(array $state, int $delta): array
    {
        if ($delta === 0) {
            return [$state, null];
        }

        $proposal = sprintf('trust %+d', $delta);
        $ruling = null;

        if (abs($delta) > 2) {
            $ruling = Ruling::adjusted($proposal, 'trust.step', 'Trust can only move 2 points per exchange.');
            $delta = max(-2, min(2, $delta));
        }

        $current = $state['npc']['trust'];
        $next = max(0, min(World::MAX_TRUST, $current + $delta));

        if ($ruling === null && $next !== $current + $delta) {
            $ruling = Ruling::adjusted($proposal, 'trust.bounds', 'Trust stays between 0 and '.World::MAX_TRUST.'.');
        }

        $state['npc']['trust'] = $next;

        return [$state, $ruling ?? Ruling::accepted($proposal, 'trust.step', "Trust {$current} → {$next}.")];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function setToll(array $state, int $amount): Ruling
    {
        $proposal = "set toll to {$amount} gold";
        $current = $state['npc']['toll'];

        if ($amount < World::TOLL_FLOOR) {
            return Ruling::rejected($proposal, 'toll.floor', 'The toll can never drop below '.World::TOLL_FLOOR.' gold.');
        }

        if ($amount > World::TOLL_CEILING) {
            return Ruling::rejected($proposal, 'toll.ceiling', 'The toll can never exceed '.World::TOLL_CEILING.' gold.');
        }

        if ($amount < $current && $state['npc']['trust'] < World::TRUST_TO_HAGGLE) {
            return Ruling::rejected($proposal, 'toll.haggle', 'Grimble only haggles with travellers he trusts (needs trust '.World::TRUST_TO_HAGGLE.", has {$state['npc']['trust']}).");
        }

        return Ruling::accepted($proposal, 'toll.range', "Toll {$current} → {$amount} gold.");
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function poseRiddle(array $state): Ruling
    {
        return match ($state['npc']['riddle']) {
            'unasked' => Ruling::accepted('pose the riddle', 'riddle.once', 'The riddle is now open. The server, not the NPC, will judge the answer.'),
            'solved' => Ruling::rejected('pose the riddle', 'riddle.once', 'The riddle has already been solved.'),
            default => Ruling::rejected('pose the riddle', 'riddle.once', 'The riddle has already been asked.'),
        };
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function giveItem(array $state, string $item): Ruling
    {
        $proposal = 'give the traveller '.($item === '' ? 'an item' : World::itemName($item));

        if (! in_array($item, $state['npc']['inventory'], true)) {
            return Ruling::rejected($proposal, 'inventory.owned', 'Grimble can only give away things he actually has.');
        }

        return Ruling::accepted($proposal, 'inventory.owned', 'Item moved from Grimble to the traveller.');
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function letPass(array $state): Ruling
    {
        $grounds = $this->passageGrounds($state);

        if ($grounds === []) {
            return Ruling::rejected(
                'open the gate',
                'gate.conditions',
                sprintf(
                    'No grounds for passage: toll %d/%d paid, locket not returned, riddle %s, trust %d/%d.',
                    $state['npc']['paid'],
                    $state['npc']['toll'],
                    $state['npc']['riddle'],
                    $state['npc']['trust'],
                    World::TRUST_TO_PASS,
                ),
            );
        }

        return Ruling::accepted('open the gate', 'gate.conditions', 'Passage granted: '.implode(', ', $grounds).'.');
    }

    /**
     * Carry out an action that has already been ruled legal.
     *
     * @param  array<string, mixed>  $state
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function perform(array $state, NpcDecision $decision): array
    {
        switch ($decision->action) {
            case NpcAction::SetToll:
                $state['npc']['toll'] = $decision->amount;
                break;
            case NpcAction::PoseRiddle:
                $state['npc']['riddle'] = 'asked';
                break;
            case NpcAction::GiveItem:
                $state['npc']['inventory'] = array_values(array_diff($state['npc']['inventory'], [$decision->item]));
                $state['inventory'][] = $decision->item;
                break;
            case NpcAction::LetPass:
                $state['location'] = World::GOAL;

                return [$state, true];
            case NpcAction::None:
                break;
        }

        return [$state, false];
    }
}
