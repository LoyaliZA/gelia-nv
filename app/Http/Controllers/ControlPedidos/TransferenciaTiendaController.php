<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\TransferenciaTiendaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class TransferenciaTiendaController extends Controller
{
    public function show(PedidoBma $pedidoBma, Request $request, TransferenciaTiendaService $service): JsonResponse
    {
        return response()->json($service->resumen($pedidoBma, $request->user()));
    }

    public function update(PedidoBma $pedidoBma, string $accion, Request $request, TransferenciaTiendaService $service): JsonResponse
    {
        $service->assertVisible($pedidoBma, $request->user());
        $reglas = [
            'version' => ['required', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ];
        $reglas += match ($accion) {
            'preparar' => ['bultos' => ['required', 'array', 'min:1', 'max:50'],
                'bultos.*.foto_bulto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']],
            'recibir' => ['bolsas_verificadas' => ['required', 'accepted']],
            'custodia' => ['almacen_id' => ['required', 'integer'], 'ubicacion' => ['required', 'string', 'max:240']],
            'entregar' => ['receptor' => ['required', 'string', 'max:160'], 'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']],
            'salida' => [],
            'gestionar' => ['observaciones' => ['required', 'string', 'max:2000']],
            'continuar_posterior' => ['observaciones' => ['required', 'string', 'max:2000']],
            default => throw ValidationException::withMessages(['accion' => 'La acción no existe.']),
        };
        try {
            return response()->json($service->ejecutar($pedidoBma, $request->user(), $accion, $request->validate($reglas)));
        } catch (\RuntimeException | \InvalidArgumentException $e) {
            throw ValidationException::withMessages(['operacion' => $e->getMessage()]);
        }
    }
}
