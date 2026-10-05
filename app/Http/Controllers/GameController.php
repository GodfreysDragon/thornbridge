<?php

namespace App\Http\Controllers;

use App\Game\Exceptions\GameBusyException;
use App\Game\Exceptions\GameOverException;
use App\Game\GameEngine;
use App\Http\Requests\PlayTurnRequest;
use App\Http\Resources\GameEventResource;
use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class GameController extends Controller
{
    public function __construct(private readonly GameEngine $engine) {}

    public function store(): JsonResponse
    {
        $game = $this->engine->start()->load('events');

        return (new GameResource($game))->response()->setStatusCode(201);
    }

    public function show(Game $game): GameResource
    {
        return new GameResource($game->load('events'));
    }

    /**
     * Play one turn. Responds with the events it produced and the new state.
     */
    public function turn(PlayTurnRequest $request, Game $game): JsonResponse
    {
        try {
            $events = $this->engine->play($game, $request->validated('input'));
        } catch (GameBusyException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (GameOverException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'events' => GameEventResource::collection($events),
            'game' => new GameResource($game->refresh()),
        ]);
    }
}
