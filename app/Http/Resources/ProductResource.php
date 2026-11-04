<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'condition'   => $this->condition,
            'subcategory' => $this->whenLoaded('subcategory', function () {
                return [
                    'id'       => $this->subcategory->id,
                    'name'     => $this->subcategory->name,
                    'category' => $this->subcategory->relationLoaded('category') ? [
                        'id'     => $this->subcategory->category->id,
                        'name'   => $this->subcategory->category->name,
                        'family' => $this->subcategory->category->relationLoaded('family') ? [
                            'id'   => $this->subcategory->category->family->id,
                            'name' => $this->subcategory->category->family->name,
                        ] : null,
                    ] : null,
                ];
            }),
            'variants'    => VariantResource::collection($this->whenLoaded('variants')),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
