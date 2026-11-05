<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBdvWebhookSecret
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredSecret = config('services.bdv.webhook_secret', env('BDV_WEBHOOK_SECRET'));

        // Si se configuró un secreto en el servidor, validar que coincida
        if (! empty($configuredSecret)) {
            $incomingSecret = $request->header('X-Webhook-Secret');

            if (! $incomingSecret || ! hash_equals((string) $configuredSecret, (string) $incomingSecret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firma o secreto de webhook inválido o ausente.',
                ], 401);
            }
        }

        return $next($request);
    }
}
