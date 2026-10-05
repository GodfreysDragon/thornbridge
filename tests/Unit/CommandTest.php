<?php

use App\Game\Command;

test('player input parses into commands', function (string $input, string $verb, string $argument) {
    $command = Command::parse($input);

    expect($command->verb)->toBe($verb)
        ->and($command->argument)->toBe($argument);
})->with([
    ['look', 'look', ''],
    ['i', 'inventory', ''],
    ['go to the forest', 'go', 'forest'],
    ['walk north', 'go', 'bridge'],
    ['go over the bridge', 'go', 'far_bank'],
    ['cross the bridge', 'go', 'far_bank'],
    ['go back', 'go', 'village'],
    ['take the silver locket', 'take', 'the silver locket'],
    ['pick up bread', 'take', 'bread'],
    ['give locket to grimble', 'give', 'locket'],
    ['offer the bread', 'give', 'the bread'],
    ['pay 5', 'pay', '5'],
    ['pay 5 gold coins', 'pay', '5'],
    ['pay everything', 'pay', ''],
    ['say Hello there', 'say', 'Hello there'],
    ['Nice bridge you have', 'say', 'Nice bridge you have'],
]);
