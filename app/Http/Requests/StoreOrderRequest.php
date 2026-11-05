<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'contact_name'     => ['required', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:255'],
            'shipping_address' => ['required', 'string'],
            'city'             => ['required', 'string', 'max:255'],
            'envio_type'       => ['required', 'in:1,2'], // 1 = Retiro en tienda / 2 = Envío nacional/domicilio
            'shipping_cost'    => ['nullable', 'numeric', 'min:0'],
            'payment_method'   => ['nullable', 'string', 'max:255'], // Por defecto: 'Pago Móvil BDV'

            // Items del carrito a comprar
            'items'               => ['required', 'array', 'min:1'],
            'items.*.variant_id'  => ['required', 'integer', 'exists:variants,id'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
        ];
    }
}
