<?php

namespace App\Services;

class BdvNotificationParser
{
    /**
     * Parsea el texto recibido de la notificación del Banco de Venezuela.
     *
     * Soporta los diferentes formatos emitidos por BDV:
     * 1) "Recibiste un PagomovilBDV de {NOMBRE} por Bs.{MONTO} bajo el numero de operacion {REF}"
     * 2) "Recibiste un PagomovilBDV por Bs.{MONTO} del {TELEFONO/ORIGEN} Ref: {REF} en fecha {FECHA} hora: {HORA}"
     *
     * @param string $rawText
     * @return array{sender_name: string|null, amount: float|null, reference: string|null}|null
     */
    public function parse(string $rawText): ?array
    {
        // Limpiar comillas iniciales o finales y espacios que a veces envía la app
        $cleanText = trim($rawText, " \t\n\r\0\x0B\"'");

        // Función para normalizar cualquier formato de monto (ej: "1,00", "1.250,50", "1,250.50")
        $normalizeAmount = function (string $rawAmount): float {
            $raw = trim($rawAmount);
            if (str_contains($raw, ',') && str_contains($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '.', $raw);
            }
            return round((float) $raw, 2);
        };

        // PATRÓN 1 (Clásico): "Recibiste un PagomovilBDV de {NOMBRE} por Bs.{MONTO} bajo el numero de operacion {REF}"
        $pattern1 = '/Recibiste\s+un\s+Pagom[oó]vilBDV\s+de\s+(.+?)\s+por\s+Bs\.?\s*([\d\.,]+)\s+bajo\s+el\s+n[uú]mero\s+de\s+operaci[oó]n\s+(\d+)/ui';
        if (preg_match($pattern1, $cleanText, $matches)) {
            return [
                'sender_name' => trim($matches[1]),
                'amount'      => $normalizeAmount($matches[2]),
                'reference'   => trim($matches[3]),
            ];
        }

        // PATRÓN 2 (Con Ref y teléfono): "Recibiste un PagomovilBDV por Bs.{MONTO} del {TELEFONO} Ref: {REF}"
        // Ej: "Recibiste un PagomovilBDV por Bs.1,00 del 0424-3662542 Ref: 231439034980 en fecha 10-11-26 hora: 17:03."
        $pattern2 = '/Recibiste\s+un\s+Pagom[oó]vilBDV\s+por\s+Bs\.?\s*([\d\.,]+)\s+del?\s+(.+?)\s+Ref:\s*(\d+)/ui';
        if (preg_match($pattern2, $cleanText, $matches)) {
            return [
                'sender_name' => trim($matches[2]),
                'amount'      => $normalizeAmount($matches[1]),
                'reference'   => trim($matches[3]),
            ];
        }

        // PATRÓN 3 (Tolerante ante variaciones menores de texto del banco):
        if (preg_match('/Pagom[oó]vilBDV/ui', $cleanText)) {
            $amount = null;
            $reference = null;
            $sender = null;

            if (preg_match('/Bs\.?\s*([\d\.,]+)/ui', $cleanText, $mAmount)) {
                $amount = $normalizeAmount($mAmount[1]);
            }

            if (preg_match('/(?:Ref:?|operaci[oó]n:?)\s*(\d+)/ui', $cleanText, $mRef)) {
                $reference = trim($mRef[1]);
            }

            if (preg_match('/del?\s+([\d\w\s\-]+?)\s+(?:Ref|en\s+fecha|bajo)/ui', $cleanText, $mSender)) {
                $sender = trim($mSender[1]);
            }

            if ($amount !== null && $reference !== null) {
                return [
                    'sender_name' => $sender ?? 'Pago Móvil BDV',
                    'amount'      => $amount,
                    'reference'   => $reference,
                ];
            }
        }

        return null;
    }
}
