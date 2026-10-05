<?php

namespace App\Ai\Contracts;

use App\Ai\Data\NpcDecision;
use App\Ai\Data\NpcPrompt;
use App\Ai\Exceptions\NpcRefusedException;

/**
 * Something that can voice the NPC and propose its next move.
 */
interface NpcBrain
{
    /**
     * @throws NpcRefusedException when the model declines to answer
     */
    public function decide(NpcPrompt $prompt): NpcDecision;
}
