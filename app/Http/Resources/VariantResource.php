<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VariantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'sku'        => $this->sku,
            'image_url'  => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'image_path' => $this->image_path,
            'stock'      => (int) $this->stock,
            'price'      => (float) $this->price,
            'features'   => $this->whenLoaded('features', function () {
                return $this->features->map(function ($feature) {
                    return [
                        'id'          => $feature->id,
                        'value'       => $feature->value,
                        'description' => $feature->description,
                        'option'      => $feature->relationLoaded('option') ? [
                            'id'   => $feature->option->id,
                            'name' => $feature->option->name,
                            'type' => $feature->option->type,
                        ] : null,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
