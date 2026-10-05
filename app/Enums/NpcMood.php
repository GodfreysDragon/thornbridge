<?php

namespace App\Enums;

enum NpcMood: string
{
    case Delighted = 'delighted';
    case Friendly = 'friendly';
    case Neutral = 'neutral';
    case Suspicious = 'suspicious';
    case Annoyed = 'annoyed';
}
