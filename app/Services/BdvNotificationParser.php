<?php

namespace App\Services;

class BdvNotificationParser
{
    /**
     * Parsea el texto recibido de la notificación del Banco de Venezuela.
     *
     * Ejemplo de texto esperado:
     * "Recibiste un PagomovilBDV de NOEL ATILIO TEJEDA RODRIGUEZ por Bs.10,00 bajo el numero de operacion 007917869024"
     *
     * @param string $rawText
     * @return array{sender_name: string|null, amount: float|null, reference: string|null}|null
     */
    public function parse(string $rawText): ?array
    {
        // Limpiar comillas iniciales o finales que a veces envía la app
        $cleanText = trim($rawText, " \t\n\r\0\x0B\"'");

        // Regex calibrada para el patrón del BDV:
        // "Recibiste un PagomovilBDV de {NOMBRE} por Bs.{MONTO} bajo el numero de operacion {REFERENCIA}"
        $pattern = '/Recibiste\s+un\s+Pagom[oó]vilBDV\s+de\s+(.+?)\s+por\s+Bs\.?\s*([\d\.,]+)\s+bajo\s+el\s+n[uú]mero\s+de\s+operaci[oó]n\s+(\d+)/ui';

        if (preg_match($pattern, $cleanText, $matches)) {
            $senderName = trim($matches[1]);
            $rawAmount  = $matches[2]; // Ej: "10,00" o "1.250,50"
            $reference  = trim($matches[3]); // Ej: "007917869024"

            // Normalizar monto en formato decimal estándar (float)
            // Si tiene puntos como separador de miles (ej: 1.250,50), eliminamos el punto y cambiamos la coma por punto
            if (str_contains($rawAmount, ',') && str_contains($rawAmount, '.')) {
                $normalizedAmount = str_replace('.', '', $rawAmount);
                $normalizedAmount = str_replace(',', '.', $normalizedAmount);
            } else {
                // Si solo tiene coma como decimal (ej: 10,00), la reemplazamos por punto
                $normalizedAmount = str_replace(',', '.', $rawAmount);
            }

            return [
                'sender_name' => $senderName,
                'amount'      => round((float) $normalizedAmount, 2),
                'reference'   => $reference,
            ];
        }

        return null;
    }
}
