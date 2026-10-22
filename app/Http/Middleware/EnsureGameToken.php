<?php

namespace App\Http\Middleware;

use App\Models\Game;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only the holder of a game's signed token may read or play it. A missing
 * or wrong token gets the same 404 as an unknown game, so ids can't be probed.
 */
class EnsureGameToken
{
    public const string HEADER = 'X-Game-Token';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $game = $request->route('game');
        $token = $request->header(self::HEADER);

        abort_unless($game instanceof Game && is_string($token) && $game->hasAccessToken($token), 404);

        return $next($request);
    }
}
