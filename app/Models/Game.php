<?php

namespace App\Models;

use App\Enums\GameStatus;
use App\Game\World;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One playthrough. The random UUID doubles as the capability to play it,
 * so there are no accounts to set up for a demo.
 *
 * @property string $id
 * @property GameStatus $status
 * @property array<string, mixed> $state
 * @property int $npc_turns
 */
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory, HasUuids;

    protected $fillable = ['status', 'state', 'npc_turns'];

    protected $attributes = [
        'status' => 'playing',
        'npc_turns' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'state' => 'array',
            'npc_turns' => 'integer',
        ];
    }

    /**
     * @return HasMany<GameEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class)->orderBy('id');
    }

    public function turnsLeft(): int
    {
        return max(0, World::MAX_NPC_TURNS - $this->npc_turns);
    }

    public function isOver(): bool
    {
        return $this->status !== GameStatus::Playing;
    }
}
