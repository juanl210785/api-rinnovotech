<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $statusLabels = [
            '1' => 'Pendiente de Pago',
            '2' => 'Pagado / En Preparación',
            '3' => 'Enviado',
            '4' => 'Entregado',
            '5' => 'Cancelado / Expirado',
        ];

        return [
            'id'               => $this->id,
            'contact_name'     => $this->contact_name,
            'phone'            => $this->phone,
            'shipping_address' => $this->shipping_address,
            'city'             => $this->city,
            'envio_type'       => $this->envio_type,
            'shipping_cost'    => (float) $this->shipping_cost,
            'total_usd'        => (float) $this->total,
            'bcv_rate'         => (float) $this->bcv_rate,
            'total_bs'         => (float) $this->total_bs,
            'status'           => $this->status,
            'status_label'     => $statusLabels[$this->status] ?? 'Desconocido',
            'payment_method'   => $this->payment_method,
            'expires_at'       => $this->expires_at?->toIso8601String(),
            'is_expired'       => $this->expires_at ? now()->greaterThan($this->expires_at) : false,
            'items'            => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
