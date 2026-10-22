<?php

use App\Http\Controllers\GameController;
use App\Http\Middleware\EnsureGameToken;
use Illuminate\Support\Facades\Route;

Route::post('/games', [GameController::class, 'store'])->middleware('throttle:new-game')->name('games.store');

Route::middleware(EnsureGameToken::class)->group(function () {
    Route::get('/games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::post('/games/{game}/turns', [GameController::class, 'turn'])->middleware('throttle:npc')->name('games.turn');
});
