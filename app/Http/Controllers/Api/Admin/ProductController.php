<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Listado paginado de productos para el panel de administración.
     */
    public function index(Request $request)
    {
        $products = Product::with([
            'subcategory.category.family',
            'variants.features.option',
        ])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($request->subcategory_id, function ($query, $subId) {
                $query->where('subcategory_id', $subId);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest('id')
            ->paginate($request->integer('per_page', 15));

        return ProductResource::collection($products);
    }

    /**
     * Crear un producto junto a sus variantes de forma atómica.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = DB::transaction(function () use ($request, $validated) {
            // 1. Crear producto base
            $product = Product::create([
                'name'           => $validated['name'],
                'description'    => $validated['description'] ?? null,
                'status'         => $validated['status'] ?? 'Activo',
                'condition'      => $validated['condition'] ?? null,
                'subcategory_id' => $validated['subcategory_id'],
            ]);

            $variantsData = $validated['variants'] ?? [];

            // 2. Si no vienen variantes, crear variante por defecto (producto simple)
            if (empty($variantsData)) {
                $imagePath = null;
                if ($request->hasFile('image')) {
                    $imagePath = $request->file('image')->store('products', 'public');
                }

                $product->variants()->create([
                    'sku'        => $validated['sku'] ?? null,
                    'price'      => $validated['price'] ?? 0.00,
                    'stock'      => $validated['stock'] ?? 0,
                    'image_path' => $imagePath,
                ]);
            } else {
                // 3. Crear cada variante con sus features e imágenes
                foreach ($variantsData as $index => $variantItem) {
                    $imagePath = null;
                    if ($request->hasFile("variants.{$index}.image")) {
                        $imagePath = $request->file("variants.{$index}.image")->store('products', 'public');
                    }

                    $variant = $product->variants()->create([
                        'sku'        => $variantItem['sku'] ?? null,
                        'price'      => $variantItem['price'],
                        'stock'      => $variantItem['stock'] ?? 0,
                        'image_path' => $imagePath,
                    ]);

                    if (! empty($variantItem['features'])) {
                        $variant->features()->sync($variantItem['features']);
                    }
                }
            }

            return $product;
        });

        $product->load([
            'subcategory.category.family',
            'variants.features.option',
        ]);

        return response()->json([
            'message' => 'Producto creado con éxito',
            'data'    => new ProductResource($product),
        ], 201);
    }

    /**
     * Ver detalle de un producto específico.
     */
    public function show(Product $product): ProductResource
    {
        $product->load([
            'subcategory.category.family',
            'variants.features.option',
        ]);

        return new ProductResource($product);
    }

    /**
     * Actualizar los datos del producto y opcionalmente sus variantes.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $product, $validated) {
            // Actualizar datos base del producto
            $product->update(array_filter([
                'name'           => $validated['name'] ?? null,
                'description'    => $request->has('description') ? $validated['description'] : null,
                'status'         => $validated['status'] ?? null,
                'condition'      => $validated['condition'] ?? null,
                'subcategory_id' => $validated['subcategory_id'] ?? null,
            ], fn ($value) => ! is_null($value)));

            // Si se envían variantes para sincronizar/actualizar
            if (isset($validated['variants']) && is_array($validated['variants'])) {
                foreach ($validated['variants'] as $index => $variantData) {
                    if (! empty($variantData['id'])) {
                        $variant = Variant::where('product_id', $product->id)->find($variantData['id']);
                        if (! $variant) {
                            continue;
                        }

                        if ($request->hasFile("variants.{$index}.image")) {
                            if ($variant->image_path && Storage::disk('public')->exists($variant->image_path)) {
                                Storage::disk('public')->delete($variant->image_path);
                            }
                            $variantData['image_path'] = $request->file("variants.{$index}.image")->store('products', 'public');
                        }

                        $variant->update(array_filter([
                            'sku'        => $variantData['sku'] ?? null,
                            'price'      => $variantData['price'] ?? null,
                            'stock'      => $variantData['stock'] ?? null,
                            'image_path' => $variantData['image_path'] ?? null,
                        ], fn ($v) => ! is_null($v)));

                        if (isset($variantData['features'])) {
                            $variant->features()->sync($variantData['features']);
                        }
                    } else {
                        // Nueva variante agregada en la actualización
                        $imagePath = null;
                        if ($request->hasFile("variants.{$index}.image")) {
                            $imagePath = $request->file("variants.{$index}.image")->store('products', 'public');
                        }

                        $newVariant = $product->variants()->create([
                            'sku'        => $variantData['sku'] ?? null,
                            'price'      => $variantData['price'],
                            'stock'      => $variantData['stock'] ?? 0,
                            'image_path' => $imagePath,
                        ]);

                        if (! empty($variantData['features'])) {
                            $newVariant->features()->sync($variantData['features']);
                        }
                    }
                }
            }
        });

        $product->load([
            'subcategory.category.family',
            'variants.features.option',
        ]);

        return response()->json([
            'message' => 'Producto actualizado con éxito',
            'data'    => new ProductResource($product),
        ]);
    }

    /**
     * Eliminar un producto y sus variantes junto con sus imágenes.
     */
    public function destroy(Product $product): JsonResponse
    {
        DB::transaction(function () use ($product) {
            foreach ($product->variants as $variant) {
                if ($variant->image_path && Storage::disk('public')->exists($variant->image_path)) {
                    Storage::disk('public')->delete($variant->image_path);
                }
            }
            $product->delete();
        });

        return response()->json([
            'message' => 'Producto y sus variantes eliminados con éxito',
        ]);
    }
}
