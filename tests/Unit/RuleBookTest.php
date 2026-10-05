<?php

use App\Ai\Data\NpcDecision;
use App\Enums\NpcAction;
use App\Enums\NpcMood;
use App\Game\RuleBook;
use App\Game\Ruling;
use App\Game\World;

function decision(NpcAction $action = NpcAction::None, int $trust = 0, int $amount = 0, string $item = ''): NpcDecision
{
    return new NpcDecision('...', NpcMood::Neutral, $trust, $action, $amount, $item);
}

/**
 * @param  array<string, mixed>  $npc
 * @return array<string, mixed>
 */
function stateWith(array $npc = []): array
{
    $state = World::initialState();
    $state['location'] = 'bridge';
    $state['npc'] = array_merge($state['npc'], $npc);

    return $state;
}

test('the gate stays shut when there are no grounds for passage', function () {
    $result = (new RuleBook)->apply(stateWith(), decision(NpcAction::LetPass));

    expect($result->passed)->toBeFalse()
        ->and($result->actionRejected())->toBeTrue()
        ->and($result->actionRuling->rule)->toBe('gate.conditions')
        ->and($result->state['location'])->toBe('bridge');
});

test('each passage ground opens the gate', function (array $npc) {
    $result = (new RuleBook)->apply(stateWith($npc), decision(NpcAction::LetPass));

    expect($result->passed)->toBeTrue()
        ->and($result->state['location'])->toBe(World::GOAL);
})->with([
    'toll paid' => [['paid' => 10, 'toll' => 10]],
    'haggled toll paid' => [['paid' => 7, 'toll' => 7]],
    'locket returned' => [['gifts' => ['locket']]],
    'riddle solved' => [['riddle' => 'solved']],
    'trust earned' => [['trust' => World::TRUST_TO_PASS]],
]);

test('trust gained this turn does not count toward passage this turn', function () {
    $result = (new RuleBook)->apply(stateWith(['trust' => World::TRUST_TO_PASS - 1]), decision(NpcAction::LetPass, trust: 2));

    expect($result->passed)->toBeFalse()
        ->and($result->state['npc']['trust'])->toBe(World::TRUST_TO_PASS + 1);
});

test('trust is clamped to its step size and bounds', function () {
    $rules = new RuleBook;

    expect($rules->apply(stateWith(['trust' => 5]), decision(trust: 9))->state['npc']['trust'])->toBe(7)
        ->and($rules->apply(stateWith(['trust' => 10]), decision(trust: 2))->state['npc']['trust'])->toBe(10)
        ->and($rules->apply(stateWith(['trust' => 1]), decision(trust: -2))->state['npc']['trust'])->toBe(0)
        ->and($rules->apply(stateWith(['trust' => 5]), decision(trust: 9))->rulings[0]->verdict)->toBe(Ruling::ADJUSTED);
});

test('the toll cannot leave its allowed range', function (int $amount, string $rule) {
    $result = (new RuleBook)->apply(stateWith(['trust' => 9]), decision(NpcAction::SetToll, amount: $amount));

    expect($result->actionRuling->rule)->toBe($rule)
        ->and($result->state['npc']['toll'])->toBe(World::BASE_TOLL);
})->with([
    'below the floor' => [World::TOLL_FLOOR - 1, 'toll.floor'],
    'free passage' => [0, 'toll.floor'],
    'above the ceiling' => [World::TOLL_CEILING + 1, 'toll.ceiling'],
]);

test('the toll can only be lowered for trusted travellers', function () {
    $rules = new RuleBook;

    $distrusted = $rules->apply(stateWith(['trust' => World::TRUST_TO_HAGGLE - 1]), decision(NpcAction::SetToll, amount: 7));
    $trusted = $rules->apply(stateWith(['trust' => World::TRUST_TO_HAGGLE]), decision(NpcAction::SetToll, amount: 7));
    $raised = $rules->apply(stateWith(['trust' => 0]), decision(NpcAction::SetToll, amount: 12));

    expect($distrusted->actionRuling->rule)->toBe('toll.haggle')
        ->and($distrusted->state['npc']['toll'])->toBe(10)
        ->and($trusted->state['npc']['toll'])->toBe(7)
        ->and($raised->state['npc']['toll'])->toBe(12);
});

test('the npc can only give away items it carries', function () {
    $rules = new RuleBook;

    $real = $rules->apply(stateWith(), decision(NpcAction::GiveItem, item: 'eel'));
    $imaginary = $rules->apply(stateWith(), decision(NpcAction::GiveItem, item: 'locket'));

    expect($real->state['inventory'])->toBe(['eel'])
        ->and($real->state['npc']['inventory'])->toBe([])
        ->and($imaginary->actionRejected())->toBeTrue()
        ->and($imaginary->state['inventory'])->toBe([]);
});

test('the riddle can only be posed once', function () {
    $rules = new RuleBook;

    expect($rules->apply(stateWith(), decision(NpcAction::PoseRiddle))->state['npc']['riddle'])->toBe('asked')
        ->and($rules->apply(stateWith(['riddle' => 'asked']), decision(NpcAction::PoseRiddle))->actionRejected())->toBeTrue()
        ->and($rules->apply(stateWith(['riddle' => 'solved']), decision(NpcAction::PoseRiddle))->actionRejected())->toBeTrue();
});

test('a rejected action still lets mood and trust through', function () {
    $result = (new RuleBook)->apply(stateWith(['trust' => 2]), new NpcDecision('Go on.', NpcMood::Friendly, 1, NpcAction::LetPass));

    expect($result->actionRejected())->toBeTrue()
        ->and($result->state['npc']['trust'])->toBe(3)
        ->and($result->state['npc']['mood'])->toBe('friendly');
});

test('decisions parse defensively', function () {
    $decision = NpcDecision::fromArray(['reply' => ' Hi ', 'mood' => 'gleeful', 'action' => ['type' => 'teleport', 'item' => ' EEL ']]);

    expect($decision->reply)->toBe('Hi')
        ->and($decision->mood)->toBe(NpcMood::Neutral)
        ->and($decision->action)->toBe(NpcAction::None)
        ->and($decision->item)->toBe('eel')
        ->and($decision->trustDelta)->toBe(0);
});

test('the structured output schema is closed and fully required', function () {
    $schema = NpcDecision::schema();

    expect($schema['additionalProperties'])->toBeFalse()
        ->and($schema['required'])->toEqualCanonicalizing(array_keys($schema['properties']))
        ->and($schema['properties']['action']['additionalProperties'])->toBeFalse()
        ->and($schema['properties']['action']['properties']['type']['enum'])->toContain('let_pass', 'set_toll');
});
