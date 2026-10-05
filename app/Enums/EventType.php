<?php

namespace App\Enums;

enum EventType: string
{
    /** Text the server writes: room descriptions, results of commands. */
    case Narration = 'narration';

    /** What the player typed. */
    case Player = 'player';

    /** A line of NPC dialogue (model-written, after rule checks). */
    case Npc = 'npc';

    /** The RuleBook's ruling on one NPC proposal. */
    case Ruling = 'ruling';
}
