<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'item_number' => $this->item_number,
            'code'        => $this->code,
            'description' => $this->description,
            'ncm'         => $this->ncm,
            'cfop'        => $this->cfop,
            'unit'        => $this->unit,
            'quantity'    => $this->quantity,
            'unit_price'  => $this->unit_price,
            'total_price' => $this->total_price,
            'category'    => new CategoryResource($this->whenLoaded('category')),
        ];
    }
}
