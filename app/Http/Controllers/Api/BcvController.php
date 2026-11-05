<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BcvService;
use Illuminate\Http\JsonResponse;

class BcvController extends Controller
{
    public function __construct(
        protected BcvService $bcvService
    ) {}

    /**
     * Obtener la tasa oficial del BCV actual para el Frontend.
     */
    public function show(): JsonResponse
    {
        $rateData = $this->bcvService->getRate();

        return response()->json([
            'success' => true,
            'data'    => $rateData,
        ]);
    }
}
