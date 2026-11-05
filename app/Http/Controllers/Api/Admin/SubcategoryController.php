<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubcategoryResource;
use App\Models\Subcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcategoryController extends Controller
{
    /**
     * Listar subcategorías (con filtro opcional por category_id).
     */
    public function index(Request $request): JsonResponse
    {
        $subcategories = Subcategory::with(['category.family', 'options.features'])
            ->when($request->category_id, function ($query, $catId) {
                $query->where('category_id', $catId);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest('id')
            ->get();

        return response()->json([
            'data' => SubcategoryResource::collection($subcategories),
        ]);
    }

    /**
     * Crear una nueva subcategoría asociada a una categoría.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $subcategory = Subcategory::create($validated);
        $subcategory->load('category.family');

        return response()->json([
            'message' => 'Subcategoría creada con éxito',
            'data'    => new SubcategoryResource($subcategory),
        ], 201);
    }

    /**
     * Ver detalle de una subcategoría específica.
     */
    public function show(Subcategory $subcategory): SubcategoryResource
    {
        $subcategory->load(['category.family', 'options.features']);

        return new SubcategoryResource($subcategory);
    }

    /**
     * Actualizar una subcategoría existente.
     */
    public function update(Request $request, Subcategory $subcategory): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
        ]);

        $subcategory->update($validated);
        $subcategory->load(['category.family', 'options.features']);

        return response()->json([
            'message' => 'Subcategoría actualizada con éxito',
            'data'    => new SubcategoryResource($subcategory),
        ]);
    }

    /**
     * Eliminar una subcategoría.
     */
    public function destroy(Subcategory $subcategory): JsonResponse
    {
        $subcategory->delete();

        return response()->json([
            'message' => 'Subcategoría eliminada con éxito',
        ]);
    }
}
