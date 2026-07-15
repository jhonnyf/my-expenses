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
            'nickname' => $this->nickname,
            'display_name' => $this->display_name,
            'is_favorite' => $this->when(isset($this->is_favorite), $this->is_favorite),
        ];
    }
}
