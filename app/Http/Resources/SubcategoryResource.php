<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubcategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'category_id' => $this->category_id,
            'category'    => $this->whenLoaded('category', function () {
                return [
                    'id'        => $this->category->id,
                    'name'      => $this->category->name,
                    'family_id' => $this->category->family_id,
                ];
            }),
            'options'     => OptionResource::collection($this->whenLoaded('options')),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
