<?php

namespace App\Http\Controllers\ControlPedidos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPedidos\ActualizarPlazosRetrasoPedidoBmaRequest;
use App\Services\ControlPedidos\PlazosRetrasoPedidoBmaConfig;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlazosRetrasoPedidoBmaController extends Controller
{
    public function index(PlazosRetrasoPedidoBmaConfig $config): Response
    {
        Gate::authorize('control_pedidos.configurar_plazos');

        return Inertia::render('ControlPedidos/Plazos/Index', [
            'plazos' => $config->obtener(),
        ]);
    }

    public function update(ActualizarPlazosRetrasoPedidoBmaRequest $request, PlazosRetrasoPedidoBmaConfig $config)
    {
        Gate::authorize('control_pedidos.configurar_plazos');

        $guardados = $config->guardar($request->validated());

        return redirect()
            ->route('control_pedidos.plazos.index')
            ->with('success', 'Plazos de retraso actualizados.')
            ->with('plazos', $guardados);
    }
}
