<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductCatalogController extends Controller
{
    /**
     * Listado público de productos con filtros avanzados y paginación.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            // Solo productos activos en la tienda pública
            ->where('status', 'Activo')
            // Cargar relaciones necesarias para renderizar la tarjeta
            ->with([
                'subcategory.category.family',
                'variants.features.option',
            ])
            // 1. Búsqueda por texto (nombre o descripción)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            // 2. Filtrado por Subcategoría
            ->when($request->subcategory_id, function ($query, $subcategoryId) {
                $query->where('subcategory_id', $subcategoryId);
            })
            // 3. Filtrado por Categoría
            ->when($request->category_id, function ($query, $categoryId) {
                $query->whereHas('subcategory', function ($q) use ($categoryId) {
                    $q->where('category_id', $categoryId);
                });
            })
            // 4. Filtrado por Familia
            ->when($request->family_id, function ($query, $familyId) {
                $query->whereHas('subcategory.category', function ($q) use ($familyId) {
                    $q->where('family_id', $familyId);
                });
            })
            // 5. Filtrado por Condición (Nuevo, Usado, etc.)
            ->when($request->condition, function ($query, $condition) {
                $query->where('condition', $condition);
            })
            // 6. Filtrado por rango de precios (afecta a sus variantes)
            ->when($request->min_price || $request->max_price, function ($query) use ($request) {
                $query->whereHas('variants', function ($q) use ($request) {
                    if ($request->min_price) {
                        $q->where('price', '>=', $request->min_price);
                    }
                    if ($request->max_price) {
                        $q->where('price', '<=', $request->max_price);
                    }
                });
            })
            // 7. Filtrado por atributos / features (ej: features=1,3 o features[]=1&features[]=3)
            ->when($request->features, function ($query, $features) {
                $featureIds = is_array($features) ? $features : explode(',', (string) $features);
                $featureIds = array_filter(array_map('intval', $featureIds));

                if (! empty($featureIds)) {
                    $query->whereHas('variants.features', function ($q) use ($featureIds) {
                        $q->whereIn('features.id', $featureIds);
                    });
                }
            })
            // 8. Ordenamiento
            ->when($request->order_by, function ($query, $orderBy) use ($request) {
                $direction = strtolower($request->order_direction ?? 'asc') === 'desc' ? 'desc' : 'asc';
                if ($orderBy === 'name') {
                    $query->orderBy('name', $direction);
                } elseif ($orderBy === 'latest') {
                    $query->latest('id');
                }
            }, function ($query) {
                $query->latest('id');
            })
            ->paginate($request->integer('per_page', 12));

        return ProductResource::collection($products);
    }

    /**
     * Detalle público de un producto específico.
     */
    public function show(Product $product): ProductResource
    {
        // Si no está activo, retornamos 404
        if ($product->status !== 'Activo') {
            abort(404, 'Producto no encontrado o inactivo');
        }

        $product->load([
            'subcategory.category.family',
            'variants.features.option',
        ]);

        return new ProductResource($product);
    }
}
