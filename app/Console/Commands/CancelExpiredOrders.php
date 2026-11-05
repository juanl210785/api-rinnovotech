<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CancelExpiredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:cancel-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancela órdenes pendientes que superaron los 20 minutos de espera y restaura el stock de sus variantes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Buscar órdenes pendientes (status = '1') cuya fecha de expiración ya pasó
        $expiredOrders = Order::where('status', '1')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->with('orderItems.variant')
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->info('No hay órdenes expiradas pendientes por procesar.');
            return Command::SUCCESS;
        }

        $count = 0;

        foreach ($expiredOrders as $order) {
            DB::transaction(function () use ($order) {
                // Rollback: Devolver el stock a cada variante reservada
                foreach ($order->orderItems as $item) {
                    if ($item->variant) {
                        $item->variant->increment('stock', $item->quantity);
                    }
                }

                // Marcar la orden como Cancelada (status = 5)
                $order->update([
                    'status' => '5',
                ]);
            });

            $count++;
            $this->line("Orden #{$order->id} cancelada y stock restaurado.");
            Log::info("Orden #{$order->id} auto-cancelada por expiración de 20 minutos. Stock restaurado.");
        }

        $this->info("Se procesaron y cancelaron {$count} órdenes expiradas.");

        return Command::SUCCESS;
    }
}
