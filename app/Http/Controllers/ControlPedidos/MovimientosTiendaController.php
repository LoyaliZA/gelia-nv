<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Models\ControlPedidos\PedidoBma;
use App\Services\ControlPedidos\MovimientosTiendaService;
use Illuminate\Http\Request;

final class MovimientosTiendaController extends Controller
{
    public function show(Request $request, PedidoBma $pedidoBma, MovimientosTiendaService $servicio)
    {
        return response()->json($servicio->resumen($pedidoBma, $request->user()));
    }

    public function update(Request $request, PedidoBma $pedidoBma, string $accion, MovimientosTiendaService $servicio)
    {
        abort_unless(in_array($accion, ['solicitar', 'autorizar', 'rechazar', 'salida', 'recepcion', 'modalidad'], true), 404);
        $datos = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'sucursal_destino_id' => [$accion === 'solicitar' ? 'required' : 'nullable', 'integer'],
            'motivo' => [in_array($accion, ['solicitar', 'modalidad']) ? 'required' : 'nullable', 'string', 'max:200'],
            'error_captura' => ['sometimes', 'boolean'],
            'evidencias' => ['nullable', 'array', 'max:8'],
            'evidencias.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'recorrido' => [$accion === 'autorizar' ? 'required' : 'nullable', 'string', 'max:500'],
            'bolsas_verificadas' => [$accion === 'recepcion' ? 'accepted' : 'nullable'],
            'almacen_id' => [$accion === 'recepcion' ? 'required' : 'nullable', 'integer'],
            'ubicacion' => [$accion === 'recepcion' ? 'required' : 'nullable', 'string', 'max:240'],
            'modalidad' => [$accion === 'modalidad' ? 'required' : 'nullable', 'in:municipal,nacional'],
        ]);

        return response()->json($servicio->ejecutar($pedidoBma, $request->user(), $accion, $datos));
    }
}
