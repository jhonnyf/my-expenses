<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'color'       => $this->color,
            'icon'        => $this->icon,
            'keywords'    => $this->keywords ?? [],
            'total_spent' => $this->when(isset($this->total_spent), $this->total_spent),
        ];
    }
}
