<?php

use App\Ai\Contracts\NpcBrain;
use App\Console\Commands\EvaluateNpc;
use App\Models\Game;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\QueuedNpcBrain;

test('every scenario behaves as expected against the scripted brain', function () {
    $this->artisan('thornbridge:eval', ['--driver' => 'scripted'])
        ->expectsOutputToContain('NPC brain: ScriptedNpcBrain')
        ->expectsOutputToContain('All '.count(EvaluateNpc::SCENARIOS).' scenarios behaved as expected.')
        ->assertSuccessful();

    expect(Game::count())->toBe(0);
});

test('the eval fails when an honest route does not get the traveller across', function () {
    app()->instance(NpcBrain::class, new QueuedNpcBrain);

    $exitCode = Artisan::call('thornbridge:eval', ['--json' => true]);
    $report = json_decode(Artisan::output(), true);

    $failedScenarios = collect($report['scenarios'])->reject(fn (array $result): bool => $result['passed'])->pluck('scenario')->all();

    expect($exitCode)->toBe(1)
        ->and($report['brain'])->toBe('QueuedNpcBrain')
        ->and($failedScenarios)->toBe(['locket returned', 'riddle solved', 'haggle and pay', 'trust earned']);
});
