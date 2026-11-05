<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\FamilyResource;
use App\Models\Category;
use App\Models\Family;
use Illuminate\Http\JsonResponse;

class NavigationController extends Controller
{
    /**
     * Obtener el árbol completo del menú (Familias -> Categorías -> Subcategorías).
     */
    public function families(): JsonResponse
    {
        $families = Family::with('categories.subcategories')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => FamilyResource::collection($families),
        ]);
    }

    /**
     * Obtener categorías con sus subcategorías anidadas.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::with('subcategories')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => CategoryResource::collection($categories),
        ]);
    }
}
