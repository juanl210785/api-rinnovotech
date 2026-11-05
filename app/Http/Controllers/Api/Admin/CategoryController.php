<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Listar categorías (con filtro opcional por family_id).
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::with(['family', 'subcategories'])
            ->when($request->family_id, function ($query, $familyId) {
                $query->where('family_id', $familyId);
            })
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest('id')
            ->get();

        return response()->json([
            'data' => CategoryResource::collection($categories),
        ]);
    }

    /**
     * Crear una nueva categoría asociada a una familia.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'family_id' => ['required', 'integer', 'exists:families,id'],
        ]);

        $category = Category::create($validated);
        $category->load('family');

        return response()->json([
            'message' => 'Categoría creada con éxito',
            'data'    => new CategoryResource($category),
        ], 201);
    }

    /**
     * Ver detalle de una categoría específica.
     */
    public function show(Category $category): CategoryResource
    {
        $category->load(['family', 'subcategories']);

        return new CategoryResource($category);
    }

    /**
     * Actualizar una categoría existente.
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'family_id' => ['sometimes', 'required', 'integer', 'exists:families,id'],
        ]);

        $category->update($validated);
        $category->load(['family', 'subcategories']);

        return response()->json([
            'message' => 'Categoría actualizada con éxito',
            'data'    => new CategoryResource($category),
        ]);
    }

    /**
     * Eliminar una categoría (en cascada elimina sus subcategorías).
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'message' => 'Categoría eliminada con éxito',
        ]);
    }
}
