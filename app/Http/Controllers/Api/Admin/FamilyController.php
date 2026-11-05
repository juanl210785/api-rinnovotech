<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FamilyResource;
use App\Models\Family;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    /**
     * Listar familias con sus categorías y subcategorías.
     */
    public function index(Request $request): JsonResponse
    {
        $families = Family::with('categories.subcategories')
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest('id')
            ->get();

        return response()->json([
            'data' => FamilyResource::collection($families),
        ]);
    }

    /**
     * Crear una nueva familia.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:families,name'],
        ]);

        $family = Family::create($validated);

        return response()->json([
            'message' => 'Familia creada con éxito',
            'data'    => new FamilyResource($family),
        ], 201);
    }

    /**
     * Ver detalle de una familia específica.
     */
    public function show(Family $family): FamilyResource
    {
        $family->load('categories.subcategories');

        return new FamilyResource($family);
    }

    /**
     * Actualizar una familia existente.
     */
    public function update(Request $request, Family $family): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:families,name,' . $family->id],
        ]);

        $family->update($validated);

        return response()->json([
            'message' => 'Familia actualizada con éxito',
            'data'    => new FamilyResource($family),
        ]);
    }

    /**
     * Eliminar una familia (en cascada elimina sus categorías y subcategorías).
     */
    public function destroy(Family $family): JsonResponse
    {
        $family->delete();

        return response()->json([
            'message' => 'Familia eliminada con éxito',
        ]);
    }
}
