<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShoppingListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'description'  => $this->description,
            'unit'         => $this->unit,
            'unit_price'   => $this->unit_price,
            'quantity'     => $this->quantity,
            'purchased_at' => $this->purchased_at,
            'created_at'   => $this->created_at,
            'issuer'       => new IssuerResource($this->whenLoaded('issuer')),
        ];
    }
}
