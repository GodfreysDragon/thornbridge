<?php

namespace App\Game\Exceptions;

use RuntimeException;

class GameOverException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This game has ended. Start a new one to play again.');
    }
}
