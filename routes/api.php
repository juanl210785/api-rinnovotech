<?php

use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\FamilyController as AdminFamilyController;
use App\Http\Controllers\Api\Admin\OptionController as AdminOptionController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\SubcategoryController as AdminSubcategoryController;
use App\Http\Controllers\Api\Admin\SubcategoryOptionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\ProductCatalogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Públicas de la Tienda (Sin Autenticación)
|--------------------------------------------------------------------------
*/
// Navegación / Menú de la tienda
Route::get('/families', [NavigationController::class, 'families']);
Route::get('/categories', [NavigationController::class, 'categories']);

// Tasa Oficial BCV del día (para banners, carrito y conversiones públicas)
Route::get('/bcv-rate', [\App\Http\Controllers\Api\BcvController::class, 'show']);

// Webhook receptor de notificaciones bancarias (NotifyHook Android)
Route::post('/webhooks/bdv', [\App\Http\Controllers\Api\BankWebhookController::class, 'handleBdv'])
    ->middleware('verify.bdv.webhook');

// Catálogo de Productos para clientes
Route::get('/products', [ProductCatalogController::class, 'index']);
Route::get('/products/{product}', [ProductCatalogController::class, 'show']);

// Autenticación
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
        // Familias, Categorías y Subcategorías
        Route::apiResource('families', AdminFamilyController::class);
        Route::apiResource('categories', AdminCategoryController::class);
        Route::apiResource('subcategories', AdminSubcategoryController::class);

        // Opciones (Atributos) y Features (Valores)
        Route::apiResource('options', AdminOptionController::class);
        Route::post('options/{option}/features', [AdminOptionController::class, 'addFeature']);
        Route::delete('features/{feature}', [AdminOptionController::class, 'destroyFeature']);

        // Vinculación de Opciones a Subcategorías
        Route::get('subcategories/{subcategory}/options', [SubcategoryOptionController::class, 'index']);
        Route::post('subcategories/{subcategory}/options', [SubcategoryOptionController::class, 'syncOptions']);

        // Productos y Variantes
        Route::apiResource('products', AdminProductController::class);
    });
});
