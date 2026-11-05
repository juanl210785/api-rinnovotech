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

    /**
     * Confirmar y conciliar el Pago Móvil con las notificaciones recibidas en bank_payments.
     */
    public function confirmPayment(Request $request, Order $order): JsonResponse
    {
        // 1. Autorización: sólo el dueño de la orden o un admin puede pagarla
        if ($order->user_id !== $request->user()->id && ! $request->user()->isAdmin()) {
            abort(403, 'No tienes autorización para pagar esta orden.');
        }

        // 2. Validación de entrada
        $request->validate([
            'reference' => ['required', 'string', 'min:4', 'max:50'],
        ], [
            'reference.required' => 'Debes ingresar el número de referencia del Pago Móvil.',
            'reference.min'      => 'Debes ingresar al menos los últimos 4 dígitos de la referencia.',
        ]);

        $referenceInput = trim($request->reference);

        // 3. Verificar estado de la orden
        if ($order->status === '2') {
            return response()->json([
                'success' => true,
                'message' => 'Esta orden ya se encuentra pagada.',
                'order'   => new OrderResource($order),
            ], 200);
        }

        if ($order->status !== '1') {
            return response()->json([
                'success' => false,
                'message' => 'Esta orden no está pendiente de pago o ya fue cancelada.',
            ], 422);
        }

        // 4. Verificar si la orden ya expiró
        if ($order->expires_at && now()->greaterThan($order->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'El tiempo de espera para esta orden (20 minutos) ha expirado. Por favor genera una nueva orden.',
            ], 422);
        }

        // 5. Conciliación atómica en Base de Datos
        $expectedBs = (float) $order->total_bs;
        $tolerance = 0.10; // Margen de céntimos ± 0.10 Bs
        $minAmount = round($expectedBs - $tolerance, 2);
        $maxAmount = round($expectedBs + $tolerance, 2);

        $payment = DB::transaction(function () use ($order, $referenceInput, $minAmount, $maxAmount) {
            // Buscar un pago no reclamado que coincida con la referencia y el monto esperado
            $matchedPayment = \App\Models\BankPayment::where('status', 'unclaimed')
                ->where(function ($query) use ($referenceInput) {
                    $query->where('reference', $referenceInput)
                          ->orWhere('reference', 'like', "%{$referenceInput}");
                })
                ->whereBetween('amount', [$minAmount, $maxAmount])
                ->lockForUpdate()
                ->first();

            if (! $matchedPayment) {
                return null;
            }

            // Marcar el pago como consumido
            $matchedPayment->update([
                'status'   => 'claimed',
                'order_id' => $order->id,
            ]);

            // Actualizar la orden a 'Pagado / En Preparación' (status = 2)
            $order->update([
                'status'         => '2',
                'payment_method' => 'Pago Móvil BDV (Conciliado Automático)',
            ]);

            return $matchedPayment;
        });

        // 6. Si el pago fue encontrado y conciliado
        if ($payment) {
            $order->load(['orderItems.product', 'orderItems.variant']);

            return response()->json([
                'success' => true,
                'message' => '¡Pago verificado y aprobado con éxito! Tu orden ha sido confirmada.',
                'payment' => [
                    'bank'        => $payment->bank,
                    'reference'   => $payment->reference,
                    'amount_bs'   => (float) $payment->amount,
                    'sender_name' => $payment->sender_name,
                ],
                'order'   => new OrderResource($order),
            ]);
        }

        // 7. Si no coincide aún (la notificación del banco todavía no llega o el monto/referencia no coinciden)
        return response()->json([
            'success' => false,
            'message' => 'Aún no hemos detectado tu pago en nuestra cuenta. Si acabas de realizar la transferencia, espera 10 a 15 segundos y presiona "Verificar Pago" nuevamente.',
            'tip'     => 'Asegúrate de haber transferido el monto exacto de Bs. ' . number_format($expectedBs, 2, ',', '.') . ' y haber colocado bien los dígitos de la referencia.',
        ], 422);
    }
}
