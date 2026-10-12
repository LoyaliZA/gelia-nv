<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GuardarAvancePreparacionService
{
    public function __construct(private RegistrarProductosPreparacionService $registrar, private ReportarIncidenciaPreparacionService $incidencias) {}

    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $usuario, array $datos): void
    {
        abort_unless($usuario->can('control_pedidos.tienda.responder'), 403);
        $rutas = [];
        try {
            DB::transaction(function () use ($tarea, $usuario, $datos, &$rutas) {
                $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
                $tarea->load('modalidad');
                if ($tarea->modalidad?->codigo === 'RECOGE_TIENDA' || $tarea->estado !== 'EN_ATENCION' || (int) $tarea->asignada_a_id !== (int) $usuario->id || (int) $tarea->version !== (int) $datos['version']) {
                    throw ValidationException::withMessages(['tarea' => 'La tarea cambió o no está asignada a usted. Actualice la preparación.']);
                }
                $motivo = trim((string) ($datos['motivo_incidencia'] ?? ''));
                $lineas = $datos['lineas'] ?? [];
                if ($lineas === [] && $motivo === '') {
                    throw ValidationException::withMessages(['lineas' => 'Registre las piezas o reporte el problema.']);
                }
                if ($lineas !== []) {
                    $rutas = $this->registrar->ejecutar($tarea, $usuario, $lineas);
                }
                $rutas = array_merge($rutas, $this->registrar->guardarGenerales($tarea, $usuario, $datos['evidencias'] ?? []));
                $updates = collect($datos)->only(['observaciones_respuesta', 'peso_real_kg', 'peso_volumetrico_kg', 'catalogo_tipo_caja_id', 'observaciones_fisicas'])->map(fn ($v) => $v === '' ? null : $v)->all();
                $updates['version'] = $tarea->version + 1;
                $tarea->update($updates);
                if ($motivo !== '') {
                    $this->incidencias->ejecutar($tarea, $usuario, 'otro', $motivo, $tarea->almacen_id, null, [], null, [], $tarea->version);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($rutas);
            throw $e;
        }
    }
}
