<?php

namespace App\Http\Resources;

use App\Models\GameEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameEvent
 */
class GameEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'text' => $this->text,
            'data' => $this->data,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
