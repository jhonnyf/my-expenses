<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'items_count' => $this->whenCounted('items'),
            'purchased_count' => $this->when(isset($this->purchased_count), $this->purchased_count),
            'items_total' => $this->when(isset($this->items_total), (float) $this->items_total),
            'items' => ShoppingListItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
