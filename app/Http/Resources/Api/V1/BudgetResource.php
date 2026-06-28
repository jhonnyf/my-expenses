<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'category_id' => $this->category_id,
            'amount'      => $this->amount,
            'spent'       => $this->when(isset($this->spent), $this->spent),
            'percentage'  => $this->when(isset($this->percentage), $this->percentage),
            'remaining'   => $this->when(isset($this->remaining), $this->remaining),
            'category'    => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
