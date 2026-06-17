<?php

namespace App\Http\Controllers\Soporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SoporteTicket;
use App\Models\SoporteConfiguracion;
use App\Models\SoporteCatalogoModulo;
use App\Models\SoporteCatalogoCategoria;
use App\Models\SoporteCatalogoPrioridad;
use App\Models\SoporteCatalogoEstado;
use App\Models\User;
use App\Services\Soporte\TicketLecturaService;

class SoporteAgenteController extends Controller
{
    public function index(Request $request, TicketLecturaService $lecturaService)
    {
        $configuracion = SoporteConfiguracion::first() ?? SoporteConfiguracion::create();

        if ($configuracion->modo_pruebas) {
            $tickets = new \Illuminate\Pagination\LengthAwarePaginator(
                $this->ticketsPrueba(),
                count($this->ticketsPrueba()),
                15,
                1,
                []
            );
        } else {
            $tickets = SoporteTicket::with(['modulo', 'categoria', 'estado', 'prioridadAsignada', 'user', 'asignadoA'])
                ->orderBy('id', 'desc')
                ->paginate(15);

            $lecturaService->annotateCollection($tickets->getCollection(), $request->user());
        }

        $props = [
            'tickets' => $tickets,
            'modoPruebas' => $configuracion->modo_pruebas,
            'prioridades' => SoporteCatalogoPrioridad::where('activo', true)->get(),
            'estados' => SoporteCatalogoEstado::where('activo', true)->get(),
        ];

        if ($request->user()->can('soporte.administrar') || $request->user()->hasRole('Super Admin')) {
            $props['configuracion'] = $configuracion;
            $props['modulos'] = SoporteCatalogoModulo::all();
            $props['categorias'] = SoporteCatalogoCategoria::all();
            $props['permisos_disponibles'] = \Spatie\Permission\Models\Permission::pluck('name');
        }

        return Inertia::render('Soporte/Agente/Index', $props);
    }

    public function show(Request $request, SoporteTicket $ticket, TicketLecturaService $lecturaService)
    {
        $ticket->load(['interacciones.user', 'user', 'modulo', 'categoria', 'estado', 'prioridadSugerida', 'prioridadAsignada', 'asignadoA']);
        $lecturaService->markAsRead($ticket, $request->user());

        $serialized = $this->serializeTicket($ticket);

        return response()->json($serialized);
    }

    public function reply(Request $request, SoporteTicket $ticket)
    {
        $request->validate([
            'mensaje' => 'required|string|max:2000',
        ]);

        $interaccion = $ticket->interacciones()->create([
            'user_id' => $request->user()->id,
            'mensaje' => $request->mensaje,
            'es_nota_interna' => false,
        ]);

        broadcast(new \App\Events\Soporte\TicketInteraccionCreatedEvent($interaccion));

        if (!$ticket->asignado_a_id) {
            $ticket->update(['asignado_a_id' => $request->user()->id]);
        }

        $ticket->loadMissing('user');

        if ($ticket->user) {
            $ticket->user->notify(new \App\Notifications\Soporte\TicketReplyNotification($interaccion, true));
        }

        return back()->with('success', 'Mensaje enviado.');
    }

    public function updatePriority(Request $request, SoporteTicket $ticket)
    {
        $request->validate([
            'prioridad_asignada_id' => 'required|exists:soporte_catalogo_prioridades,id',
        ]);

        $prioridad = SoporteCatalogoPrioridad::find($request->prioridad_asignada_id);
        $slaService = app(\App\Services\Soporte\SLAService::class);

        $ticket->update([
            'prioridad_asignada_id' => $request->prioridad_asignada_id,
            'fecha_vencimiento_sla' => $prioridad
                ? $slaService->calculateDueDate($prioridad->tiempo_respuesta_horas, $ticket->created_at)
                : $ticket->fecha_vencimiento_sla,
        ]);

        return back()->with('success', 'Prioridad actualizada.');
    }

    public function updateStatus(Request $request, SoporteTicket $ticket)
    {
        $request->validate([
            'estado_id' => 'required|exists:soporte_catalogo_estados,id',
        ]);

        $estado = SoporteCatalogoEstado::find($request->estado_id);
        $updates = ['estado_id' => $request->estado_id];

        if ($estado && in_array($estado->nombre, ['Resuelto', 'Cerrado'], true)) {
            $updates['fecha_resolucion'] = now();
        }

        $ticket->update($updates);

        return back()->with('success', 'Estado actualizado.');
    }

    public function assignAgent(Request $request, SoporteTicket $ticket)
    {
        $request->validate([
            'asignado_a_id' => 'nullable|exists:users,id',
        ]);

        $asignadoId = $request->input('asignado_a_id', $request->user()->id);

        if ($asignadoId !== $request->user()->id) {
            $target = User::permission('soporte.gestionar')->where('id', $asignadoId)->first();
            if (!$target || $target->excluir_asignacion_tickets) {
                return back()->with('error', 'El usuario seleccionado no puede recibir tickets.');
            }
        }

        $estadoAsignadoId = SoporteCatalogoEstado::where('nombre', 'Asignado')->value('id');

        $ticket->update([
            'asignado_a_id' => $asignadoId,
            'estado_id' => $estadoAsignadoId ?: $ticket->estado_id,
        ]);

        return back()->with('success', 'Agente asignado correctamente.');
    }

    private function serializeTicket(SoporteTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'titulo' => $ticket->titulo,
            'descripcion' => $ticket->descripcion,
            'estado_id' => $ticket->estado_id,
            'prioridad_sugerida_id' => $ticket->prioridad_sugerida_id,
            'prioridad_asignada_id' => $ticket->prioridad_asignada_id,
            'created_at' => $ticket->created_at,
            'fecha_vencimiento_sla' => $ticket->fecha_vencimiento_sla,
            'user' => $ticket->user,
            'asignadoA' => $ticket->asignadoA,
            'modulo' => $ticket->modulo,
            'categoria' => $ticket->categoria,
            'estado' => $ticket->estado,
            'prioridadSugerida' => $ticket->prioridadSugerida,
            'prioridadAsignada' => $ticket->prioridadAsignada,
            'interacciones' => $ticket->interacciones,
        ];
    }

    private function ticketsPrueba(): array
    {
        return [
            (object) [
                'id' => 99991,
                'titulo' => '[MODO PRUEBA] Error en acceso a sistema',
                'user' => (object) ['name' => 'Usuario Prueba'],
                'modulo' => (object) ['nombre' => 'Login'],
                'categoria' => (object) ['nombre' => 'Problema de Acceso'],
                'estado' => (object) ['nombre' => 'Abierto', 'color' => '#3b82f6'],
                'prioridadAsignada' => (object) ['nombre' => 'Alta', 'color' => '#ef4444'],
                'asignadoA' => (object) ['name' => 'Agente Demo'],
                'created_at' => now()->subHours(2)->toIso8601String(),
                'has_unread' => true,
            ],
        ];
    }
}
