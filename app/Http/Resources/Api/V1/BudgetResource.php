<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'amount' => $this->amount,
            'spent' => $this->when(isset($this->spent), $this->spent),
            'percentage' => $this->when(isset($this->percentage), $this->percentage),
            'remaining' => $this->when(isset($this->remaining), $this->remaining),
            // Chaves presentes mesmo quando null (sem base de comparação/projeção).
            'previous_spent' => $this->when(isset($this->spent), $this->previous_spent),
            'delta_pct' => $this->when(isset($this->spent), $this->delta_pct),
            'projected' => $this->when(isset($this->spent), $this->projected),
            'projected_percentage' => $this->when(isset($this->spent), $this->projected_percentage),
            'exceeds_on' => $this->when(isset($this->spent), $this->exceeds_on),
            'daily_available' => $this->when(isset($this->spent), $this->daily_available),
            'category' => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
