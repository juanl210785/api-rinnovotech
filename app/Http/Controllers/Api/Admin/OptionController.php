<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeatureResource;
use App\Http\Resources\OptionResource;
use App\Models\Feature;
use App\Models\Option;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OptionController extends Controller
{
    /**
     * Listar todas las opciones con sus valores (features).
     */
    public function index(): JsonResponse
    {
        $options = Option::with('features')->latest('id')->get();

        return response()->json([
            'data' => OptionResource::collection($options),
        ]);
    }

    /**
     * Crear una nueva opción con o sin features iniciales.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'type'                   => ['required', 'string', 'max:255'], // ej: text, color, select
            'features'               => ['nullable', 'array'],
            'features.*.value'       => ['required_with:features', 'string', 'max:255'],
            'features.*.description' => ['nullable', 'string'],
        ]);

        $option = DB::transaction(function () use ($validated) {
            $option = Option::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
            ]);

            if (! empty($validated['features'])) {
                foreach ($validated['features'] as $item) {
                    $option->features()->create([
                        'value'       => $item['value'],
                        'description' => $item['description'] ?? null,
                    ]);
                }
            }

            return $option;
        });

        $option->load('features');

        return response()->json([
            'message' => 'Opción creada con éxito',
            'data'    => new OptionResource($option),
        ], 201);
    }

    /**
     * Mostrar una opción específica con sus features.
     */
    public function show(Option $option): OptionResource
    {
        $option->load('features');

        return new OptionResource($option);
    }

    /**
     * Actualizar una opción existente.
     */
    public function update(Request $request, Option $option): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $option->update($validated);
        $option->load('features');

        return response()->json([
            'message' => 'Opción actualizada con éxito',
            'data'    => new OptionResource($option),
        ]);
    }

    /**
     * Eliminar una opción y en cascada sus features.
     */
    public function destroy(Option $option): JsonResponse
    {
        $option->delete();

        return response()->json([
            'message' => 'Opción eliminada con éxito',
        ]);
    }

    /**
     * Agregar un nuevo valor (Feature) a una opción existente.
     */
    public function addFeature(Request $request, Option $option): JsonResponse
    {
        $validated = $request->validate([
            'value'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $feature = $option->features()->create($validated);

        return response()->json([
            'message' => 'Valor agregado con éxito a la opción',
            'data'    => new FeatureResource($feature),
        ], 201);
    }

    /**
     * Eliminar un Feature individual.
     */
    public function destroyFeature(Feature $feature): JsonResponse
    {
        $feature->delete();

        return response()->json([
            'message' => 'Valor (Feature) eliminado con éxito',
        ]);
    }
}
