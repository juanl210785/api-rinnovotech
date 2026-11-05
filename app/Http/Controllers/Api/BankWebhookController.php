<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankPayment;
use App\Services\BdvNotificationParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BankWebhookController extends Controller
{
    public function __construct(
        protected BdvNotificationParser $parser
    ) {}

    /**
     * Endpoint receptor para las notificaciones enviadas por la app NotifyHook del Android.
     *
     * Payload esperado de NotifyHook:
     * {
     *   "rule": "Bank",
     *   "package": "com.bancodevenezuela.bdvdigital",
     *   "app": "BDV",
     *   "title": "PagomóvilBDV recibido",
     *   "text": "Recibiste un PagomovilBDV de ... por Bs.10,00 bajo el numero de operacion ...",
     *   "timestamp": "2026-11-05T11:22:39.146-04:00"
     * }
     */
    public function handleBdv(Request $request): JsonResponse
    {
        $rawText = $request->input('text') ?? $request->input('body') ?? '';

        if (empty($rawText)) {
            return response()->json([
                'success' => false,
                'message' => 'El cuerpo de la notificación está vacío.',
            ], 400);
        }

        // Parsea el texto del BDV
        $parsed = $this->parser->parse($rawText);

        if (! $parsed) {
            Log::info('Notificación BDV ignorada (no coincide con el formato de pago recibido): ' . $rawText);

            return response()->json([
                'success' => false,
                'message' => 'La notificación no corresponde a un Pago Móvil recibido válido.',
            ], 200); // 200 para que la app no reintente indefinidamente
        }

        // Evitar duplicados por reenvío de la misma notificación
        $existing = BankPayment::where('reference', $parsed['reference'])
            ->where('bank', 'BDV')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'El pago con esta referencia ya fue registrado previamente.',
                'data'    => $existing,
            ], 200);
        }

        // Guardar el pago en estado 'unclaimed' (listo para ser reclamado por la orden)
        $paymentDate = $request->filled('timestamp') ? rescue(fn () => \Carbon\Carbon::parse($request->timestamp), now(), false) : now();

        $bankPayment = BankPayment::create([
            'bank'         => 'BDV',
            'sender_name'  => $parsed['sender_name'],
            'reference'    => $parsed['reference'],
            'amount'       => $parsed['amount'],
            'raw_message'  => $rawText,
            'status'       => 'unclaimed',
            'payment_date' => $paymentDate,
        ]);

        Log::info("Pago Móvil BDV registrado: Ref {$bankPayment->reference} por Bs. {$bankPayment->amount} de {$bankPayment->sender_name}");

        return response()->json([
            'success' => true,
            'message' => 'Pago Móvil registrado con éxito en bank_payments.',
            'data'    => $bankPayment,
        ], 201);
    }
}
