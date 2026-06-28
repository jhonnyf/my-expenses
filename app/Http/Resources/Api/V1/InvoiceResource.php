<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'access_key'       => $this->access_key,
            'number'           => $this->number,
            'series'           => $this->series,
            'issued_at'        => $this->issued_at,
            'environment'      => $this->environment,
            'total_icms_base'  => $this->total_icms_base,
            'total_icms'       => $this->total_icms,
            'total_products'   => $this->total_products,
            'total_amount'     => $this->total_amount,
            'total_taxes'      => $this->total_taxes,
            'created_at'       => $this->created_at,
            'issuer'           => new IssuerResource($this->whenLoaded('issuer')),
            'items'            => InvoiceItemResource::collection($this->whenLoaded('items')),
            'payments'         => InvoicePaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
