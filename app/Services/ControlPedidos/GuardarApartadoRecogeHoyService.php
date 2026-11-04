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
use App\Support\ControlPedidos\InstruccionEntrega;

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
        InstruccionEntrega::assertEjecutable($tarea);
        abort_unless($usuario->can('control_pedidos.tienda.apartado.separar') || $usuario->can('control_pedidos.tienda.responder'), 403);

        $rutas = [];
        try {
            return DB::transaction(function () use ($tarea, $usuario, $datos, $confirmar, &$rutas) {
                $tarea = InstruccionEntrega::bloquearParaEjecutar($tarea);
                $tarea->load(['modalidad', 'pedido', 'cumplimientoFisico']);
                if ((int) $tarea->asignada_a_id !== (int) $usuario->id) {
                    throw ValidationException::withMessages(['tarea' => 'Solo el auxiliar que tomó la tarea puede registrar y confirmar las piezas.']);
                }
                if ($confirmar && \App\Support\ControlPedidos\ApartadoT01::aplica($tarea->pedido) && $tarea->pedido->consultaCerrada()
                    && $tarea->cumplimientoFisico?->estado === PedidoBmaCumplimientoFisico::ESTADO_APARTADO_CONFIRMADO) {
                    return $tarea->cumplimientoFisico;
                }
                if ($tarea->modalidad?->codigo !== CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA
                    || $tarea->estado !== PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION) {
                    throw ValidationException::withMessages(['estado' => 'Tome la solicitud de recoge en tienda antes de registrar las piezas.']);
                }
                $tarea->load('pedido');
                if ($tarea->pedido?->esConsultaDisponibilidadTienda()
                    && isset($tarea->pedido->solicitud_preparacion_json['revision_disponibilidad']['estado'])
                    && (! $tarea->pedido->consultaCerrada() || $tarea->pedido->tieneAjusteTiendaPendiente())) {
                    throw ValidationException::withMessages(['estado' => 'La respuesta ya fue enviada. Espere la confirmación de Ventas o atienda el ajuste solicitado.']);
                }
                $this->asegurar->ejecutar($tarea);
                $apartado = PedidoBmaCumplimientoFisico::query()->where('pedido_bma_tarea_preparacion_id', $tarea->id)->lockForUpdate()->firstOrFail();
                if ((int) $apartado->version !== (int) ($datos['version'] ?? 0)) {
                    throw ValidationException::withMessages(['version' => 'Otra persona modificó el apartado. Actualice la página antes de guardar.']);
                }
                if (! in_array($apartado->estado, [PedidoBmaCumplimientoFisico::ESTADO_POR_SEPARAR, PedidoBmaCumplimientoFisico::ESTADO_SEPARADA], true)) {
                    throw ValidationException::withMessages(['estado' => 'Este apartado ya no admite cambios.']);
                }
                $lineas = array_values(array_filter($datos['lineas'] ?? [], 'is_array'));
                $motivo = trim((string) ($datos['motivo_incidencia'] ?? ''));
                $evidencias = array_values(array_filter(
                    $datos['evidencias'] ?? [],
                    fn ($archivo) => $archivo instanceof \Illuminate\Http\UploadedFile
                ));
                $documental = $confirmar && \App\Support\ControlPedidos\ApartadoT01::aplica($tarea->pedido) && $tarea->pedido->consultaCerrada();
                if (! $documental && $lineas === [] && $motivo === '' && $evidencias === []) {
                    throw ValidationException::withMessages(['lineas' => 'Registre piezas, adjunte la foto del conjunto o describa el problema de la solicitud.']);
                }
                if ($confirmar && ! $documental && $lineas === []) {
                    throw ValidationException::withMessages(['lineas' => 'Identifique los productos y cantidades físicos antes de confirmar la separación.']);
                }
                $antes = $tarea->productos()->get()->toArray();
                if (! $documental && $lineas !== []) {
                    $this->assertCatalogoDelAlmacen($tarea, $lineas, $confirmar);
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

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function assertCatalogoDelAlmacen(PedidoBmaTareaPreparacion $tarea, array $lineas, bool $confirmar): void
    {
        if (! $tarea->almacen_id) {
            return;
        }

        foreach ($lineas as $linea) {
            $productoId = (int) ($linea['producto_id'] ?? 0);
            if ($productoId < 1) {
                continue;
            }
            $enAlmacen = DB::table('inventarios')
                ->where('producto_id', $productoId)
                ->where('almacen_id', $tarea->almacen_id)
                ->exists();
            if (($linea['estado_fisico'] ?? '') === 'sin_existencia' && (int) ($linea['cantidad'] ?? 0) === 0) continue;
            if (! $confirmar && ! $enAlmacen && ! DB::table('inventarios')->where('producto_id', $productoId)->exists()) continue;
            $registrada = ! empty($linea['id']) ? $tarea->productos()->find($linea['id']) : null;
            if (! $enAlmacen && $registrada && \App\Models\SolicitudTraspasoProducto::query()->where('tarea_producto_receptor_id', $registrada->id)->exists()) {
                app(\App\Services\Traspasos\AbastecerPreparacionService::class)->asegurarSatisfecho($tarea);
                continue;
            }
            if (! $enAlmacen) {
                throw ValidationException::withMessages([
                    'lineas' => 'Seleccione un producto del almacén de esta tarea.',
                ]);
            }
        }
    }
}
