<?php

namespace App\Game\Exceptions;

use RuntimeException;

/**
 * Another turn for the same game is still running. Turns are serialised so a
 * double-submitted "pay 5" cannot be applied twice against the same state.
 */
class GameBusyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Grimble is still thinking about your last move.');
    }
}
