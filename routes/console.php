<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas Programadas (Task Scheduling)
|--------------------------------------------------------------------------
| Revisa y cancela automáticamente órdenes pendientes que hayan superado
| los 20 minutos de espera, restaurando el stock a los productos.
*/
Schedule::command('orders:cancel-expired')->everyMinute();
