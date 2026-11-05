<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'family_id'     => $this->family_id,
            'family'        => $this->whenLoaded('family', function () {
                return [
                    'id'   => $this->family->id,
                    'name' => $this->family->name,
                ];
            }),
            'subcategories' => SubcategoryResource::collection($this->whenLoaded('subcategories')),
            'created_at'    => $this->created_at?->toIso8601String(),
            'updated_at'    => $this->updated_at?->toIso8601String(),
        ];
    }
}
