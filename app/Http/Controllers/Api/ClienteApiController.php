<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClienteApiController extends Controller
{
    // --- NUEVO ENDPOINT: Búsqueda y Listado en Tiempo Real ---
    public function index(Request $request): JsonResponse
    {
        $termino = $request->query('q', '');

        $clientes = Cliente::with('listaDescuento')
            ->when($termino, function($query, $termino) {
                // Filtramos por número o por nombre
                $query->where('numero_cliente', 'like', "%{$termino}%")
                      ->orWhere('nombre', 'like', "%{$termino}%");
            })
            ->limit(50) // Límite para no saturar la memoria del navegador
            ->get()
            ->map(function($cliente) {
                return [
                    'id' => $cliente->id,
                    'numero_cliente' => $cliente->numero_cliente,
                    'nombre' => $cliente->nombre,
                    'es_heredado' => (bool) $cliente->es_heredado,
                    'lista_actual' => $cliente->listaDescuento->nombre ?? 'Sin Lista'
                ];
            });

        return response()->json($clientes);
    }

    // --- ENDPOINT ANTERIOR: Búsqueda Exacta ---
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
            'lista_actual' => $cliente->listaDescuento->nombre ?? 'Sin Lista'
        ]);
    }
}