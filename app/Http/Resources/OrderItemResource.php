<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'product_id'  => $this->product_id,
            'product_name'=> $this->product?->name,
            'variant_id'  => $this->variant_id,
            'variant_sku' => $this->variant?->sku,
            'quantity'    => (int) $this->quantity,
            'price'       => (float) $this->price,
            'subtotal'    => round(((float) $this->price) * ((int) $this->quantity), 2),
        ];
    }
}
