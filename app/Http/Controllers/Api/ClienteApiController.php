<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClienteApiController extends Controller
{
    // --- SECCIÓN: BÚSQUEDA Y LISTADO EN TIEMPO REAL ---
    public function index(Request $request): JsonResponse
    {
        $termino = $request->query('q', '');

        $clientes = Cliente::with('listaDescuento')
            ->when($termino, function($query, $termino) {
                $query->where('numero_cliente', 'like', "%{$termino}%")
                      ->orWhere('nombre', 'like', "%{$termino}%");
            })
            ->limit(50)
            ->get()
            ->map(function($cliente) {
                return [
                    'id' => $cliente->id,
                    'numero_cliente' => $cliente->numero_cliente,
                    'nombre' => $cliente->nombre,
                    'es_heredado' => (bool) $cliente->es_heredado,
                    'lista_actual_id' => $cliente->lista_actual_id, // Añadido para comparación exacta
                    'lista_actual' => $cliente->listaDescuento->nombre ?? 'Sin Lista',
                    'monto_venta_actual' => (float) $cliente->monto_venta_actual // Añadido para el motor de decisión
                ];
            });

        return response()->json($clientes);
    }

    // --- SECCIÓN: BÚSQUEDA EXACTA ---
    public function show($numero): JsonResponse
    {
        $cliente = Cliente::with('listaDescuento')
            ->where('numero_cliente', $numero)
            ->first();

        if (!$cliente) {
            return response()->json(['encontrado' => false], 404);
        }

        return response()->json([
            'encontrado' => true,
            'id' => $cliente->id,
            'nombre' => $cliente->nombre,
            'es_heredado' => (bool) $cliente->es_heredado,
            'lista_actual_id' => $cliente->lista_actual_id, // Añadido
            'lista_actual' => $cliente->listaDescuento->nombre ?? 'Sin Lista',
            'monto_venta_actual' => (float) $cliente->monto_venta_actual // Añadido
        ]);
    }
}