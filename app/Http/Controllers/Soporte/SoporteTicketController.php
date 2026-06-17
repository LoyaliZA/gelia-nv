<?php

namespace App\Http\Controllers\Soporte;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SoporteTicket;
use App\Models\SoporteCatalogoModulo;
use App\Models\SoporteCatalogoCategoria;
use App\Models\SoporteCatalogoPrioridad;
use App\Models\SoporteCatalogoEstado;
use App\Models\SoporteConfiguracion;
use App\Services\Soporte\TicketAssignmentService;
use App\Services\Soporte\SLAService;
use App\Services\Soporte\TicketLecturaService;

class SoporteTicketController extends Controller
{
    public function index(Request $request, TicketLecturaService $lecturaService)
    {
        $config = SoporteConfiguracion::first();

        if ($config && $config->modo_pruebas) {
            $tickets = new \Illuminate\Pagination\LengthAwarePaginator(
                $this->ticketsPrueba(),
                count($this->ticketsPrueba()),
                15,
                1,
                []
            );
        } else {
            $tickets = SoporteTicket::where('user_id', $request->user()->id)
                ->with(['modulo', 'categoria', 'estado', 'prioridadAsignada', 'asignadoA'])
                ->orderBy('id', 'desc')
                ->paginate(15);

            $lecturaService->annotateCollection($tickets->getCollection(), $request->user());
        }

        $modulos = SoporteCatalogoModulo::where('activo', true)->get()->filter(function ($modulo) use ($request) {
            return empty($modulo->permiso_requerido) || $request->user()->can($modulo->permiso_requerido);
        })->values();
        $categorias = SoporteCatalogoCategoria::where('activo', true)->get();
        $prioridades = SoporteCatalogoPrioridad::where('activo', true)->get();

        return Inertia::render('Soporte/Tickets/Index', [
            'tickets' => $tickets,
            'modoPruebas' => $config ? $config->modo_pruebas : false,
            'modulos' => $modulos,
            'categorias' => $categorias,
            'prioridades' => $prioridades,
        ]);
    }

    public function show(Request $request, SoporteTicket $ticket, TicketLecturaService $lecturaService)
    {
        if ($ticket->user_id !== $request->user()->id) {
            abort(403);
        }

        $ticket->load(['interacciones.user', 'user', 'modulo', 'categoria', 'estado', 'prioridadSugerida', 'prioridadAsignada', 'asignadoA']);
        $lecturaService->markAsRead($ticket, $request->user());

        return response()->json($this->serializeTicket($ticket));
    }

    public function reply(Request $request, SoporteTicket $ticket)
    {
        if ($ticket->user_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'mensaje' => 'required|string|max:2000',
        ]);

        $interaccion = $ticket->interacciones()->create([
            'user_id' => $request->user()->id,
            'mensaje' => $request->mensaje,
            'es_nota_interna' => false,
        ]);

        broadcast(new \App\Events\Soporte\TicketInteraccionCreatedEvent($interaccion));

        $ticket->loadMissing('asignadoA');

        if ($ticket->asignadoA) {
            $ticket->asignadoA->notify(new \App\Notifications\Soporte\TicketReplyNotification($interaccion, false));
        }

        return back()->with('success', 'Mensaje enviado.');
    }

    public function store(Request $request, TicketAssignmentService $assignmentService, SLAService $slaService)
    {
        $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'required|string',
            'modulo_id' => 'required|exists:soporte_catalogo_modulos,id',
            'categoria_id' => 'required|exists:soporte_catalogo_categorias,id',
            'prioridad_id' => 'required|exists:soporte_catalogo_prioridades,id',
        ]);

        $config = SoporteConfiguracion::first();
        if ($config && $config->modo_pruebas) {
            return redirect()->route('soporte.tickets.index')->with('success', 'Ticket de prueba simulado (modo pruebas activo, no se guardó en BD).');
        }

        $estadoId = SoporteCatalogoEstado::where('nombre', 'Abierto')->value('id') ?? 1;
        $prioridadId = (int) $request->prioridad_id;
        $prioridad = SoporteCatalogoPrioridad::find($prioridadId);
        $asignadoAId = $assignmentService->assignNextAgent();

        $fechaVencimiento = $prioridad
            ? $slaService->calculateDueDate($prioridad->tiempo_respuesta_horas)
            : null;

        $ticket = SoporteTicket::create([
            'user_id' => $request->user()->id,
            'modulo_id' => $request->modulo_id,
            'categoria_id' => $request->categoria_id,
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'estado_id' => $estadoId,
            'prioridad_sugerida_id' => $prioridadId,
            'prioridad_asignada_id' => $prioridadId,
            'asignado_a_id' => $asignadoAId,
            'fecha_vencimiento_sla' => $fechaVencimiento,
        ]);

        $interaccionInicial = $ticket->interacciones()->create([
            'user_id' => $request->user()->id,
            'mensaje' => $request->descripcion,
            'es_nota_interna' => false,
        ]);

        broadcast(new \App\Events\Soporte\TicketInteraccionCreatedEvent($interaccionInicial));

        $ticket->load(['user', 'modulo', 'categoria', 'estado', 'prioridadAsignada', 'asignadoA', 'interacciones.user']);

        if ($ticket->asignadoA) {
            $ticket->asignadoA->notify(new \App\Notifications\Soporte\TicketCreatedNotification($ticket));
        }

        broadcast(new \App\Events\Soporte\TicketCreatedEvent($ticket));

        return redirect()->route('soporte.tickets.index')->with('success', 'Ticket creado correctamente.');
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
                'modulo' => (object) ['nombre' => 'Login'],
                'categoria' => (object) ['nombre' => 'Problema de Acceso'],
                'estado' => (object) ['nombre' => 'Abierto', 'color' => '#3b82f6'],
                'prioridadAsignada' => (object) ['nombre' => 'Alta', 'color' => '#ef4444'],
                'asignadoA' => (object) ['name' => 'Agente Demo'],
                'created_at' => now()->subHours(2)->toIso8601String(),
                'has_unread' => true,
            ],
            (object) [
                'id' => 99992,
                'titulo' => '[MODO PRUEBA] Solicitud de nueva característica',
                'modulo' => (object) ['nombre' => 'Reportes'],
                'categoria' => (object) ['nombre' => 'Solicitud de Mejora'],
                'estado' => (object) ['nombre' => 'Resuelto', 'color' => '#10b981'],
                'prioridadAsignada' => (object) ['nombre' => 'Baja', 'color' => '#3b82f6'],
                'asignadoA' => (object) ['name' => 'Agente Demo'],
                'created_at' => now()->subDays(1)->toIso8601String(),
                'has_unread' => false,
            ],
        ];
    }
}
