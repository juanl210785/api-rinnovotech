<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare inputs before validation (decode JSON strings if sent as multipart/form-data).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->variants)) {
            $decoded = json_decode($this->variants, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['variants' => $decoded]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'status'         => ['nullable', 'string', 'in:Activo,Inactivo'],
            'condition'      => ['nullable', 'string', 'max:255'],
            'subcategory_id' => ['required', 'integer', 'exists:subcategories,id'],

            // Datos base cuando es producto simple (sin variantes)
            'price'          => ['nullable', 'numeric', 'min:0'],
            'stock'          => ['nullable', 'integer', 'min:0'],
            'sku'            => ['nullable', 'string', 'max:255'],
            'image'          => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],

            // Datos de variantes si es producto configurable
            'variants'               => ['nullable', 'array'],
            'variants.*.sku'         => ['nullable', 'string', 'max:255'],
            'variants.*.price'       => ['required_with:variants', 'numeric', 'min:0'],
            'variants.*.stock'       => ['nullable', 'integer', 'min:0'],
            'variants.*.features'    => ['nullable', 'array'],
            'variants.*.features.*'  => ['integer', 'exists:features,id'],
            'variants.*.image'       => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ];
    }
}
