<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OptionResource;
use App\Models\Subcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcategoryOptionController extends Controller
{
    /**
     * Obtener las opciones y features asociados a una subcategoría.
     */
    public function index(Subcategory $subcategory): JsonResponse
    {
        $subcategory->load('options.features');

        return response()->json([
            'subcategory' => [
                'id'   => $subcategory->id,
                'name' => $subcategory->name,
            ],
            'options'     => OptionResource::collection($subcategory->options),
        ]);
    }

    /**
     * Sincronizar o asignar qué opciones pertenecen a esta subcategoría.
     * Ejemplo de body:
     * {
     *   "options": [1, 2, 3]
     * }
     */
    public function syncOptions(Request $request, Subcategory $subcategory): JsonResponse
    {
        $validated = $request->validate([
            'options'   => ['required', 'array'],
            'options.*' => ['integer', 'exists:options,id'],
        ]);

        $subcategory->options()->sync($validated['options']);
        $subcategory->load('options.features');

        return response()->json([
            'message' => 'Opciones asignadas a la subcategoría con éxito',
            'options' => OptionResource::collection($subcategory->options),
        ]);
    }
}
