<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Variant;
use App\Services\BcvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(
        protected BcvService $bcvService
    ) {}

    /**
     * Listado de órdenes del cliente autenticado.
     */
    public function index(Request $request)
    {
        $orders = $request->user()->orders()
            ->with(['orderItems.product', 'orderItems.variant'])
            ->latest('id')
            ->paginate($request->integer('per_page', 10));

        return OrderResource::collection($orders);
    }

    /**
     * Detalle de una orden específica del cliente.
     */
    public function show(Request $request, Order $order)
    {
        // Asegurar que el usuario solo pueda ver sus propias órdenes (o que sea admin)
        if ($order->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403, 'No tienes autorización para ver esta orden.');
        }

        $order->load(['orderItems.product', 'orderItems.variant']);

        return new OrderResource($order);
    }

    /**
     * Crear una nueva orden con reserva de stock por 20 minutos y cálculo en Bolívares.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        // 1. Obtener la tasa oficial del día del BCV
        $bcvData = $this->bcvService->getRate();
        $bcvRate = (float) $bcvData['rate'];

        // 2. Transacción de base de datos para reserva atómica
        $order = DB::transaction(function () use ($user, $validated, $bcvRate) {
            $subtotalUsd = 0.00;
            $itemsToCreate = [];

            // Verificar stock físico y bloquear filas con lockForUpdate
            foreach ($validated['items'] as $item) {
                $variant = Variant::where('id', $item['variant_id'])
                    ->lockForUpdate()
                    ->with('product')
                    ->first();

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'items' => ["La variante seleccionada (ID: {$item['variant_id']}) no existe."],
                    ]);
                }

                if ($variant->stock < $item['quantity']) {
                    $productName = $variant->product?->name ?? 'Producto';
                    throw ValidationException::withMessages([
                        'items' => ["Stock insuficiente para '{$productName}' (Disponible: {$variant->stock}, Solicitado: {$item['quantity']})."],
                    ]);
                }

                $itemPrice = (float) $variant->price;
                $itemSubtotal = $itemPrice * $item['quantity'];
                $subtotalUsd += $itemSubtotal;

                // Descontar temporalmente el stock (reserva activa)
                $variant->decrement('stock', $item['quantity']);

                $itemsToCreate[] = [
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'quantity'   => $item['quantity'],
                    'price'      => $itemPrice,
                ];
            }

            $shippingCost = (float) ($validated['shipping_cost'] ?? 0.00);
            $totalUsd = $subtotalUsd + $shippingCost;
            $totalBs  = round($totalUsd * $bcvRate, 2);

            // 3. Crear cabecera de la Orden con expiración a 20 minutos
            $order = Order::create([
                'user_id'          => $user->id,
                'contact_name'     => $validated['contact_name'],
                'phone'            => $validated['phone'],
                'shipping_address' => $validated['shipping_address'],
                'city'             => $validated['city'],
                'envio_type'       => $validated['envio_type'],
                'shipping_cost'    => $shippingCost,
                'total'            => $totalUsd,
                'bcv_rate'         => $bcvRate,
                'total_bs'         => $totalBs,
                'status'           => '1', // 1 = Pendiente
                'payment_method'   => $validated['payment_method'] ?? 'Pago Móvil BDV',
                'expires_at'       => now()->addMinutes(20),
            ]);

            // 4. Crear los detalles de la orden
            foreach ($itemsToCreate as $itemData) {
                $order->orderItems()->create($itemData);
            }

            return $order;
        });

        $order->load(['orderItems.product', 'orderItems.variant']);

        // 5. Retornar la orden junto con los datos bancarios para realizar el pago
        return response()->json([
            'message' => 'Orden creada con éxito. Realiza el Pago Móvil antes de que expire la reserva (20 minutos).',
            'order'   => new OrderResource($order),
            'payment_instructions' => [
                'bank'          => env('BDV_COMMERCE_BANK', '0102 - Banco de Venezuela'),
                'phone'         => env('BDV_COMMERCE_PHONE', '04121234567'),
                'id_number'     => env('BDV_COMMERCE_ID', 'V-12345678'),
                'account_name'  => env('BDV_COMMERCE_NAME', 'Rinnovotech Store'),
                'amount_to_pay' => [
                    'usd'      => (float) $order->total,
                    'bs'       => (float) $order->total_bs,
                    'bcv_rate' => (float) $order->bcv_rate,
                ],
                'note' => 'Transfiere el monto exacto en Bolívares. Una vez realizado el pago, confirma ingresando el número de referencia.',
            ],
        ], 201);
    }
}
