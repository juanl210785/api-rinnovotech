<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BcvService
{
    /**
     * Clave de caché para la tasa activa.
     */
    protected const CACHE_KEY = 'bcv_rate_current';

    /**
     * Clave de respaldo persistente por si falla la API externa.
     */
    protected const FALLBACK_CACHE_KEY = 'bcv_rate_last_known';

    /**
     * Tiempo de vida de la caché (2 horas = 7200 segundos).
     */
    protected const CACHE_TTL = 7200;

    /**
     * Obtener la tasa oficial del BCV (con caché, API externa y respaldo seguro).
     *
     * @return array{rate: float, currency: string, source: string, last_updated: string}
     */
    public function getRate(): array
    {
        // 1. Si está en caché activa, devolver directamente
        if (Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        // 2. Intentar consultar la API externa
        try {
            $response = Http::timeout(6)->get('https://ve.dolarapi.com/v1/dolares/oficial');

            if ($response->successful()) {
                $data = $response->json();
                $rate = (float) ($data['promedio'] ?? 0);

                if ($rate > 0) {
                    $payload = [
                        'rate'         => round($rate, 4),
                        'currency'     => 'VES',
                        'source'       => 'BCV Oficial (vía DolarApi)',
                        'last_updated' => $data['fechaActualizacion'] ?? now()->toIso8601String(),
                    ];

                    // Guardar en caché activa por 2 horas
                    Cache::put(self::CACHE_KEY, $payload, self::CACHE_TTL);
                    // Guardar en caché de respaldo permanente
                    Cache::forever(self::FALLBACK_CACHE_KEY, $payload);

                    return $payload;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo conectar a la API del BCV: ' . $e->getMessage());
        }

        // 3. Si falló la API, buscar el último valor conocido de respaldo
        if (Cache::has(self::FALLBACK_CACHE_KEY)) {
            $lastKnown = Cache::get(self::FALLBACK_CACHE_KEY);
            $lastKnown['source'] = 'Última tasa conocida (Offline Fallback)';
            return $lastKnown;
        }

        // 4. Si es la primera vez y no hay internet, valor por defecto de configuración o .env
        $fallbackRate = (float) config('services.bcv.default_rate', 50.00);

        return [
            'rate'         => round($fallbackRate, 4),
            'currency'     => 'VES',
            'source'       => 'Valor configurado por defecto',
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Forzar la actualización inmediata de la tasa borrando la caché.
     */
    public function refreshRate(): array
    {
        Cache::forget(self::CACHE_KEY);
        return $this->getRate();
    }
}
