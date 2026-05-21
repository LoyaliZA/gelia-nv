<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Entregas\CotizadorEntregaService;
use Illuminate\Http\JsonResponse;
use Exception;

class CotizacionEntregaController extends Controller
{
    /**
     * Servicio inyectado para el cálculo matemático de entregas.
     */
    protected CotizadorEntregaService $cotizadorService;

    public function __construct(CotizadorEntregaService $cotizadorService)
    {
        $this->cotizadorService = $cotizadorService;
    }

    /**
     * Recibe coordenadas del cliente, procesa la cotización y retorna el costo.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calcularCosto(Request $request): JsonResponse
    {
        // 1. Validación estricta de seguridad en la entrada de datos
        $request->validate([
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
        ]);

        try {
            // 2. Delegación al motor matemático
            $resultado = $this->cotizadorService->cotizar(
                (float) $request->latitud,
                (float) $request->longitud
            );

            return response()->json($resultado, 200);

        } catch (Exception $e) {
            // 3. Manejo de excepciones específicas (Ej. Fuera de tolerancia)
            return response()->json([
                'es_valido' => false,
                'mensaje' => $e->getMessage()
            ], 422);
        }
    }
}