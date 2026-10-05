<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Game\World;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => GameStatus::Playing,
            'state' => World::initialState(),
            'npc_turns' => 0,
        ];
    }

    /**
     * Standing at the bridge, facing the troll.
     */
    public function atBridge(): static
    {
        return $this->withState(['location' => 'bridge']);
    }

    /**
     * Merge overrides into the game state (one level deep for "npc").
     *
     * @param  array<string, mixed>  $overrides
     */
    public function withState(array $overrides): static
    {
        return $this->state(function (array $attributes) use ($overrides): array {
            $state = $attributes['state'];

            if (isset($overrides['npc'])) {
                $overrides['npc'] = array_merge($state['npc'], $overrides['npc']);
            }

            return ['state' => array_merge($state, $overrides)];
        });
    }
}
