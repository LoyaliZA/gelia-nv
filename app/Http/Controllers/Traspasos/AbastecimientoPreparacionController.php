<?php

namespace App\Http\Controllers\Traspasos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Traspasos\AbastecimientoPreparacionRequest;
use App\Models\Almacen;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\SolicitudTraspaso;
use App\Services\Traspasos\AbastecerPreparacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AbastecimientoPreparacionController extends Controller
{
    public function index(Request $request, AbastecerPreparacionService $servicio)
    {
        $actor = $request->user();
        abort_unless($actor->can('control_pedidos.tienda.responder') || $actor->can('traspasos.responder') || $actor->can('control_pedidos.preparacion.corregir'), 403);
        $sucursales = $actor->idsSucursalesOperables()->all();
        $fisicos = Almacen::whereIn('sucursal_id', $sucursales)->pluck('id');
        if ($actor->can('control_pedidos.cedis')) {
            $fisicos = $fisicos->merge(Almacen::whereNull('sucursal_id')->where('activo', true)->whereDoesntHave('tipoAlmacen', fn ($q) => $q->where('nombre', 'PDV'))->where('codigo', '!=', 'PDV')->pluck('id'));
        }
        $tarea = null;
        if ($request->filled('tarea')) {
            $t = PedidoBmaTareaPreparacion::with(['almacen', 'pedido'])->findOrFail($request->integer('tarea'));
            $servicio->autorizar($actor, $t->almacen, 'control_pedidos.tienda.responder');
            $tarea = ['id' => $t->id, 'version' => (int) $t->version, 'estado' => $t->estado,
                'folio' => $t->pedido->folio_remision, 'almacen' => $t->almacen->nombre, 'productos' => $servicio->pendientes($t)];
        }
        $traspasos = SolicitudTraspaso::with(['almacenOrigen', 'almacenDestino', 'tareaReceptora.pedido', 'operaciones.actor'])
            ->where('proposito', SolicitudTraspaso::PROPOSITO_ABASTECIMIENTO)
            ->where(fn ($q) => $q->whereIn('almacen_origen_id', $fisicos)->orWhereIn('almacen_destino_id', $fisicos))
            ->when($tarea, fn ($q) => $q->where('tarea_receptora_id', $tarea['id']))->latest()->paginate(15)
            ->through(function ($s) use ($servicio, $actor, $fisicos) {
                $abierta = $s->tareaReceptora && ! $s->tareaReceptora->trashed() && in_array($s->tareaReceptora->estado, ['PENDIENTE', 'EN_ATENCION', 'CON_INCIDENCIA'], true);
                $responsable = (int) $s->tareaReceptora?->asignada_a_id === (int) $actor->id;

                return ['id' => $s->id, 'folio' => $s->folio, 'version' => $s->version_operativa,
                    'origen' => $s->almacenOrigen->nombre, 'destino' => $s->almacenDestino->nombre,
                    'tarea_id' => $s->tarea_receptora_id, 'pedido' => $s->tareaReceptora?->pedido?->folio_remision,
                    'salida_at' => $s->salida_fisica_at?->toIso8601String(), 'lineas' => $servicio->resumen($s),
                    'puede_salida' => $abierta && $actor->can('traspasos.responder') && $fisicos->contains($s->almacen_origen_id),
                    'puede_llegada' => $abierta && $responsable && $actor->can('control_pedidos.tienda.responder') && $fisicos->contains($s->almacen_destino_id),
                    'puede_resolver' => $abierta && $actor->can('control_pedidos.preparacion.corregir') && $fisicos->contains($s->almacen_destino_id),
                    'operaciones' => $s->operaciones->map(fn ($op) => ['id' => $op->id, 'tipo' => $op->tipo, 'actor_id' => $op->actor_id, 'actor_nombre' => $op->actor?->name,
                        'fecha' => $op->created_at->toIso8601String(), 'observacion' => $op->observacion,
                        'lineas' => $op->lineas, 'tiene_evidencia' => (bool) $op->evidencia_path])];
            });

        return Inertia::render('Traspasos/Abastecimiento', ['tarea' => $tarea, 'traspasos' => $traspasos,
            'almacenes' => $tarea ? Almacen::where('activo', true)->where('visible_en_traspasos', true)->get(['id', 'nombre', 'codigo']) : []]);
    }

    public function crear(AbastecimientoPreparacionRequest $request, PedidoBmaTareaPreparacion $tarea, AbastecerPreparacionService $servicio)
    {
        $d = $request->validated();
        $servicio->crear($tarea, $request->user(), (int) $d['almacen_origen_id'], $d['lineas'], $d['idempotency_key'], (int) $d['version']);

        return back()->with('success', 'Traslado de abastecimiento solicitado.');
    }

    public function operar(AbastecimientoPreparacionRequest $request, SolicitudTraspaso $traspaso, AbastecerPreparacionService $servicio)
    {
        $d = $request->validated();
        $servicio->operar($traspaso, $request->user(), $d['tipo'], $d['lineas'] ?? [], $d['idempotency_key'], (int) $d['version'], $request->file('evidencia'), $d['observacion'] ?? null, $d['revisiones'] ?? []);

        return back()->with('success', 'Operación física registrada.');
    }

    public function evidencia(Request $request, SolicitudTraspaso $traspaso, int $operacion, AbastecerPreparacionService $servicio)
    {
        abort_unless($traspaso->esAbastecimiento(), 404);
        $actor = $request->user();
        $origen = $traspaso->almacenOrigen;
        $destino = $traspaso->almacenDestino;
        $enOrigen = $origen->sucursal_id ? $actor->idsSucursalesOperables()->contains((int) $origen->sucursal_id) : $actor->can('control_pedidos.cedis');
        abort_unless(($enOrigen && $actor->can('traspasos.responder')) || ($actor->idsSucursalesOperables()->contains((int) $destino->sucursal_id) && ($actor->can('control_pedidos.tienda.responder') || $actor->can('control_pedidos.preparacion.corregir'))), 403);
        $op = $traspaso->operaciones()->findOrFail($operacion);
        abort_unless($op->evidencia_path, 404);

        return Storage::disk('public')->response($op->evidencia_path);
    }
}
