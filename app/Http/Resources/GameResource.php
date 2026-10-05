<?php

namespace App\Http\Resources;

use App\Game\RuleBook;
use App\Game\World;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Game
 */
class GameResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->state;
        $npc = $state['npc'];
        $place = World::locations()[$state['location']];
        $item = fn (string $key): array => ['key' => $key, 'name' => World::itemName($key)];

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'turns_left' => $this->turnsLeft(),
            'location' => [
                'key' => $state['location'],
                'name' => $place['name'],
                'exits' => array_map(fn (string $key): array => ['key' => $key, 'name' => World::locations()[$key]['name']], $place['exits']),
                'items' => array_map($item, $state['ground'][$state['location']]),
                'has_npc' => $state['location'] === 'bridge',
            ],
            'player' => [
                'gold' => $state['gold'],
                'inventory' => array_map($item, $state['inventory']),
            ],
            'npc' => [
                'name' => World::NPC_NAME,
                'mood' => $npc['mood'],
                'trust' => $npc['trust'],
                'toll' => $npc['toll'],
                'paid' => $npc['paid'],
                'riddle' => $npc['riddle'],
                'inventory' => array_map($item, $npc['inventory']),
                'gifts' => array_map($item, $npc['gifts']),
            ],
            'passage_grounds' => (new RuleBook)->passageGrounds($state),
            'rules' => [
                'max_trust' => World::MAX_TRUST,
                'trust_to_haggle' => World::TRUST_TO_HAGGLE,
                'trust_to_pass' => World::TRUST_TO_PASS,
                'toll_floor' => World::TOLL_FLOOR,
                'toll_ceiling' => World::TOLL_CEILING,
                'max_npc_turns' => World::MAX_NPC_TURNS,
            ],
            'events' => GameEventResource::collection($this->whenLoaded('events')),
        ];
    }
}
