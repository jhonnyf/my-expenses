<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssuerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cnpj' => $this->cnpj,
            'name' => $this->name,
            'street' => $this->street,
            'street_number' => $this->street_number,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'nickname' => $this->nickname,
            'display_name' => $this->display_name,
            'is_favorite' => $this->when(isset($this->is_favorite), $this->is_favorite),
            'purchase_count' => $this->when(isset($this->purchase_count), $this->purchase_count),
            'total_spent' => $this->when(isset($this->total_spent), $this->total_spent),
            'last_purchase_at' => $this->when(isset($this->last_purchase_at), $this->last_purchase_at),
            'average_ticket' => $this->when(
                isset($this->purchase_count, $this->total_spent) && $this->purchase_count > 0,
                fn () => round($this->total_spent / $this->purchase_count, 2)
            ),
        ];
    }
}
