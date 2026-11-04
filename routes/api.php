<?php

use App\Http\Controllers\Api\Admin\OptionController as AdminOptionController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\SubcategoryOptionController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Públicas de Autenticación
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Rutas Protegidas por Sanctum (Cualquier usuario autenticado)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /*
    |--------------------------------------------------------------------------
    | Rutas Exclusivas de Administradores
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Gestión de Opciones (Atributos) y Features (Valores)
        Route::apiResource('options', AdminOptionController::class);
        Route::post('options/{option}/features', [AdminOptionController::class, 'addFeature']);
        Route::delete('features/{feature}', [AdminOptionController::class, 'destroyFeature']);

        // Vinculación de Opciones a Subcategorías
        Route::get('subcategories/{subcategory}/options', [SubcategoryOptionController::class, 'index']);
        Route::post('subcategories/{subcategory}/options', [SubcategoryOptionController::class, 'syncOptions']);

        // Gestión de Productos y Variantes
        Route::apiResource('products', AdminProductController::class);
    });
});
