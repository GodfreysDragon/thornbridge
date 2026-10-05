<?php

namespace App\Enums;

/**
 * The only levers the model can pull. Each one is a *proposal* that the
 * RuleBook accepts or rejects against the authoritative game state.
 */
enum NpcAction: string
{
    case None = 'none';
    case SetToll = 'set_toll';
    case PoseRiddle = 'pose_riddle';
    case GiveItem = 'give_item';
    case LetPass = 'let_pass';
}
