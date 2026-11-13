<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\Solicitudes\RecuperarAplicacionClienteJob;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\SolicitudTag;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecuperacionAplicacionClienteController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('clientes.recuperar_aplicacion_autorizada'), 403);
        $estados = array_filter([
            CatalogoEstadoSolicitud::idDe('Respondida'),
            CatalogoEstadoSolicitud::idDe('Verificada'),
        ]);

        $solicitudes = SolicitudTag::query()
            ->with('cliente:id,nombre,numero_cliente,es_inactivo,es_menudeo,lista_actual_id')
            ->whereIn('catalogo_estado_solicitud_id', $estados)
            ->where(function ($consulta) {
                $consulta->whereNull('aplicacion_cliente')
                    ->orWhereIn('aplicacion_cliente', ['pendiente', 'fallida']);
            })
            ->where(function ($consulta) {
                $consulta->whereNull('motivo_incorrecta')->orWhere('motivo_incorrecta', '!=', 'vencimiento_pago');
            })
            ->orderByDesc('id')
            ->paginate(25);

        return Inertia::render('Admin/RecuperacionAplicacionesCliente', [
            'solicitudes' => $solicitudes,
        ]);
    }

    public function aplicar(Request $request, SolicitudTag $solicitud)
    {
        abort_unless($request->user()?->can('clientes.recuperar_aplicacion_autorizada'), 403);
        if ($solicitud->motivo_incorrecta === 'vencimiento_pago') {
            return back()->withErrors(['solicitud' => 'Un pago vencido no se recupera. Hay que abrir una solicitud nueva.']);
        }
        $solicitud->forceFill([
            'aplicacion_cliente' => $solicitud->aplicacion_cliente ?: 'pendiente',
        ])->save();
        RecuperarAplicacionClienteJob::dispatchSync($solicitud->id);

        return back()->with('success', 'Se intentó aplicar la autorización seleccionada.');
    }
}
