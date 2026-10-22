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
 * One playthrough. There are no accounts: whoever holds the game's signed
 * access token (see accessToken()) may play it.
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

    /**
     * The capability to read and play this game: an HMAC of its id under the
     * app key, so it never needs storing and rotating APP_KEY revokes them all.
     */
    public function accessToken(): string
    {
        return hash_hmac('sha256', "game:{$this->id}", (string) config('app.key'));
    }

    public function hasAccessToken(string $token): bool
    {
        return hash_equals($this->accessToken(), $token);
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
