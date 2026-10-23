<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NPC Brain Driver
    |--------------------------------------------------------------------------
    |
    | "claude" voices the NPC with the Anthropic API. "scripted" uses an
    | offline keyword-driven NPC so you can play without an API key.
    |
    */

    'driver' => env('AI_DRIVER', 'claude'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Per-IP limits on the endpoints that can spend tokens. Each game is also
    | capped at World::MAX_NPC_TURNS model turns.
    |
    */

    'rate_limits' => [
        'turns_per_minute' => (int) env('AI_TURNS_PER_MINUTE', 20),
        'games_per_hour' => (int) env('AI_GAMES_PER_HOUR', 30),

        /*
        | Behind an edge proxy every request arrives from the proxy, so limits
        | keyed on the socket address would be shared by all visitors. Name a
        | header the edge overwrites (e.g. CF-Connecting-IP) to key on the
        | real visitor instead. Leave it unset anywhere clients can reach the
        | app directly, because then they could send the header themselves.
        */
        'client_ip_header' => env('RATE_LIMIT_CLIENT_IP_HEADER'),
    ],

];
