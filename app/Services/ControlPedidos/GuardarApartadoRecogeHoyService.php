<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBmaCumplimientoEvento;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GuardarApartadoRecogeHoyService
{
    public function __construct(
        private RegistrarProductosPreparacionService $registrar,
        private AsegurarCumplimientoFisicoService $asegurar,
        private RegistrarEventoCumplimientoFisicoService $eventos,
        private ConfirmarApartadoRecogeHoyService $confirmar,
        private ReportarIncidenciaPreparacionService $incidencias,
    ) {}

    public function ejecutar(PedidoBmaTareaPreparacion $tarea, User $usuario, array $datos, bool $confirmar = false): PedidoBmaCumplimientoFisico
    {
        abort_unless($usuario->can('control_pedidos.tienda.apartado.separar') || $usuario->can('control_pedidos.tienda.responder'), 403);

        $rutas = [];
        try {
            return DB::transaction(function () use ($tarea, $usuario, $datos, $confirmar, &$rutas) {
                $tarea = PedidoBmaTareaPreparacion::query()->lockForUpdate()->findOrFail($tarea->id);
                $tarea->load('modalidad');
                if ((int) $tarea->asignada_a_id !== (int) $usuario->id) {
                    throw ValidationException::withMessages(['tarea' => 'Solo el auxiliar que tomó la tarea puede registrar y confirmar las piezas.']);
                }
                if ($tarea->modalidad?->codigo !== CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA
                    || $tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION) {
                    throw ValidationException::withMessages(['estado' => 'Tome la solicitud de recoge en tienda antes de registrar las piezas.']);
                }
                $this->asegurar->ejecutar($tarea);
                $apartado = PedidoBmaCumplimientoFisico::query()->where('pedido_bma_tarea_preparacion_id', $tarea->id)->lockForUpdate()->firstOrFail();
                if ((int) $apartado->version !== (int) ($datos['version'] ?? 0)) {
                    throw ValidationException::withMessages(['version' => 'Otra persona modificó el apartado. Actualice la página antes de guardar.']);
                }
                if (! in_array($apartado->estado, [PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR, PedidoBmaCumplimientoFisico::ESTADO_SEPARADA], true)) {
                    throw ValidationException::withMessages(['estado' => 'Este apartado ya no admite cambios.']);
                }
                $lineas = $datos['lineas'] ?? [];
                $motivo = trim((string) ($datos['motivo_incidencia'] ?? ''));
                if ($lineas === [] && $motivo === '') {
                    throw ValidationException::withMessages(['lineas' => 'Registre piezas o describa el problema de la solicitud.']);
                }
                $antes = $tarea->productos()->get()->toArray();
                if ($lineas !== []) {
                    $rutas = $this->registrar->ejecutar($tarea, $usuario, $lineas);
                }
                $rutas = array_merge($rutas, $this->registrar->guardarGenerales($tarea, $usuario, $datos['evidencias'] ?? []));
                $apartado->update(['cantidad' => (int) $tarea->productos()->sum('cantidad_encontrada'), 'vence_at' => $tarea->fecha_limite, 'ubicacion' => trim((string) ($datos['ubicacion'] ?? $apartado->ubicacion)) ?: null, 'version' => $apartado->version + 1]);
                $this->eventos->ejecutar($apartado, PedidoBmaCumplimientoEvento::TIPO_DETALLE_REPORTADO, $usuario,
                    'preparacion:'.$tarea->id.':'.$apartado->version, 'Preparación registrada en PDV.',
                    ['antes' => $antes, 'piezas' => $tarea->productos()->get()->toArray()]);
                $motivo = trim((string) ($datos['motivo_incidencia'] ?? ''));
                if ($motivo !== '') {
                    $this->incidencias->ejecutar($tarea, $usuario, 'producto_no_encontrado', $motivo, $tarea->almacen_id, null, [], null, [], $tarea->version);
                }
                if ($confirmar) {
                    if ($motivo !== '') {
                        throw ValidationException::withMessages(['estado' => 'Resuelva la incidencia antes de confirmar el apartado.']);
                    }

                    return $this->confirmar->ejecutar($tarea->fresh(), $usuario, (int) $apartado->version);
                }

                return $apartado->fresh();
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($rutas);
            throw $e;
        }
    }
}
