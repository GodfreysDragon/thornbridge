<?php

namespace App\Ai\Exceptions;

use RuntimeException;

/**
 * The model declined the request (stop_reason "refusal") and no fallback
 * model was able to complete it. The engine turns this into a silent NPC.
 */
class NpcRefusedException extends RuntimeException
{
    public static function withCategory(?string $category): self
    {
        return new self($category
            ? "The model declined to voice the NPC (category: {$category})."
            : 'The model declined to voice the NPC.');
    }
}
