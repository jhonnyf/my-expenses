<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'plan' => $this->plan->value,
            'status' => $this->status->value,
            'started_at' => $this->started_at,
            'expires_at' => $this->expires_at,
        ];
    }
}
