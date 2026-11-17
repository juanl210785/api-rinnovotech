<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Variant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Listado maestro de órdenes (clientes y administradores) con filtros avanzados.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()
            ->with([
                'user',
                'orderItems.product',
                'orderItems.variant.features.option',
                'bankPayments',
            ]);

        // 1. Filtro por estado ('1', '2', '3', '4', '5')
        if ($request->filled('status')) {
            $query->where('status', (string) $request->status);
        }

        // 2. Filtro por modalidad de envío ('1' = retiro, '2' = nacional)
        if ($request->filled('envio_type')) {
            $query->where('envio_type', (string) $request->envio_type);
        }

        // 3. Búsqueda multidimensional inteligente
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                // Búsqueda por ID directo
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }

                $q->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('shipping_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  })
                  ->orWhereHas('bankPayments', function ($pq) use ($search) {
                      $pq->where('reference', 'like', "%{$search}%")
                         ->orWhere('sender_name', 'like', "%{$search}%");
                  });
            });
        }

        // 4. Filtro por rango de fechas
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Ordenamiento
        $sortBy = in_array($request->sort_by, ['id', 'total', 'created_at', 'status'])
            ? $request->sort_by
            : 'id';
        $direction = strtolower($request->sort_direction) === 'asc' ? 'asc' : 'desc';

        $orders = $query->orderBy($sortBy, $direction)
            ->paginate($request->integer('per_page', 15));

        return OrderResource::collection($orders);
    }

    /**
     * Métricas y KPIs vivos para el panel financiero de órdenes.
     */
    public function stats(): JsonResponse
    {
        $totalOrders = Order::count();

        // Conteo por cada estado
        $pendingCount   = Order::where('status', '1')->count();
        $paidCount      = Order::where('status', '2')->count();
        $shippedCount   = Order::where('status', '3')->count();
        $deliveredCount = Order::where('status', '4')->count();
        $cancelledCount = Order::where('status', '5')->count();

        // Facturación consolidada (Pagado, Enviado o Entregado)
        $revenueUsd = (float) Order::whereIn('status', ['2', '3', '4'])->sum('total');
        $revenueBs  = (float) Order::whereIn('status', ['2', '3', '4'])->sum('total_bs');

        // Monto en reserva pendiente
        $pendingUsd = (float) Order::where('status', '1')->sum('total');
        $pendingBs  = (float) Order::where('status', '1')->sum('total_bs');

        return response()->json([
            'total_orders'    => $totalOrders,
            'counts'          => [
                'pending'   => $pendingCount,
                'paid'      => $paidCount,
                'shipped'   => $shippedCount,
                'delivered' => $deliveredCount,
                'cancelled' => $cancelledCount,
            ],
            'financials'      => [
                'revenue_usd' => round($revenueUsd, 2),
                'revenue_bs'  => round($revenueBs, 2),
                'pending_usd' => round($pendingUsd, 2),
                'pending_bs'  => round($pendingBs, 2),
            ],
        ]);
    }

    /**
     * Detalle completo auditado de una orden.
     */
    public function show(Order $order): OrderResource
    {
        $order->load([
            'user',
            'orderItems.product',
            'orderItems.variant.features.option',
            'bankPayments',
        ]);

        return new OrderResource($order);
    }

    /**
     * Actualizar estado de una orden con manejo de stock atómico.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status'        => ['required', 'string', 'in:1,2,3,4,5'],
            'notes'         => ['nullable', 'string', 'max:500'],
            'tracking_code' => ['nullable', 'string', 'max:100'],
        ]);

        $oldStatus = $order->status;
        $newStatus = (string) $validated['status'];

        if ($oldStatus === $newStatus) {
            return response()->json([
                'success' => true,
                'message' => 'El estado de la orden no ha cambiado.',
                'order'   => new OrderResource($order),
            ]);
        }

        DB::transaction(function () use ($order, $oldStatus, $newStatus, $validated) {
            // CASO A: Se cambia a CANCELADO/EXPIRADO (status 5) desde un estado activo -> Rollback de Stock
            if ($newStatus === '5' && $oldStatus !== '5') {
                foreach ($order->orderItems as $item) {
                    if ($item->variant) {
                        $item->variant->increment('stock', $item->quantity);
                    }
                }
            }

            // CASO B: Se reactiva desde CANCELADO (status 5) a un estado activo -> Descontar Stock
            if ($oldStatus === '5' && $newStatus !== '5') {
                foreach ($order->orderItems as $item) {
                    if ($item->variant) {
                        if ($item->variant->stock < $item->quantity) {
                            throw ValidationException::withMessages([
                                'status' => ["No hay stock suficiente para reactivar la orden con el equipo: {$item->product?->name}"],
                            ]);
                        }
                        $item->variant->decrement('stock', $item->quantity);
                    }
                }
            }

            // CASO C: Marcar manualmente como PAGADO (status 2)
            $updateData = ['status' => $newStatus];
            if ($newStatus === '2' && ! str_contains((string) $order->payment_method, 'Conciliado')) {
                $updateData['payment_method'] = 'Pago Validado Manualmente (Admin)';
            }

            $order->update($updateData);
        });

        $order->load([
            'user',
            'orderItems.product',
            'orderItems.variant.features.option',
            'bankPayments',
        ]);

        $statusLabels = [
            '1' => 'Pendiente de Pago',
            '2' => 'Pagado / En Preparación',
            '3' => 'Enviado',
            '4' => 'Entregado',
            '5' => 'Cancelado / Expirado',
        ];

        return response()->json([
            'success' => true,
            'message' => "La Orden #{$order->id} se actualizó a '{$statusLabels[$newStatus]}'.",
            'order'   => new OrderResource($order),
        ]);
    }
}
