<?php

namespace App\Services\Traspasos;

use App\Models\Almacen;
use App\Models\AuditoriaSolicitudTraspaso;
use App\Models\CatalogoEstadoSolicitud;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\SolicitudTraspaso;
use App\Models\SolicitudTraspasoProducto;
use App\Models\TraspasoOperacion;
use App\Models\User;
use App\Support\ControlPedidos\AlmacenPdv;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Reutiliza solicitudes, estados y revisión del traspaso. El registro de llegadas no mueve stock. */
class AbastecerPreparacionService
{
    public function autorizar(User $actor, Almacen $almacen, string $permiso): void
    {
        abort_unless($actor->can($permiso), 403);
        if ($almacen->sucursal_id) {
            abort_unless($actor->idsSucursalesOperables()->contains((int) $almacen->sucursal_id), 403);
        } else {
            abort_unless(! AlmacenPdv::esAlmacen($almacen) && $actor->can('control_pedidos.cedis'), 403);
        }
    }

    public function resumen(SolicitudTraspaso $traspaso): array
    {
        $traspaso->loadMissing(['productos', 'operaciones']);

        return $traspaso->productos->map(function ($p) use ($traspaso) {
            $buenas = 0;
            $danadas = 0;
            $rechazadas = 0;
            $faltantes = 0;
            foreach ($traspaso->operaciones as $op) {
                foreach ($op->lineas as $linea) {
                    if ((int) $linea['id'] !== (int) $p->id) {
                        continue;
                    }
                    if ($op->tipo === 'LLEGADA') {
                        $buenas += (int) $linea['buenas'];
                        $danadas += (int) $linea['danadas'];
                        $faltantes = max(0, $faltantes - (int) $linea['buenas'] - (int) $linea['danadas']);
                    }
                    if ($op->tipo === 'FALTANTE') {
                        $faltantes += (int) $linea['cantidad'];
                    }
                    if ($op->tipo === 'RECHAZO_AUTORIZADO') {
                        $rechazadas += (int) $linea['cantidad'];
                    }
                }
            }

            return ['id' => $p->id, 'sku' => $p->sku, 'descripcion' => $p->descripcion,
                'tarea_producto_id' => $p->tarea_producto_receptor_id, 'esperadas' => (int) $p->piezas,
                'buenas' => $buenas, 'danadas' => $danadas, 'rechazadas' => $rechazadas,
                'pendientes' => max(0, (int) $p->piezas - $buenas - $danadas),
                'faltantes_reportados' => $faltantes, 'incidencia_abierta' => max(0, $danadas - $rechazadas)];
        })->all();
    }

    public function pendientes(PedidoBmaTareaPreparacion $tarea): array
    {
        $reservadas = [];
        $enTransito = [];
        foreach (SolicitudTraspaso::where('tarea_receptora_id', $tarea->id)->where('proposito', SolicitudTraspaso::PROPOSITO_ABASTECIMIENTO)->get() as $s) {
            foreach ($this->resumen($s) as $p) {
                $reservadas[$p['tarea_producto_id']] = ($reservadas[$p['tarea_producto_id']] ?? 0)
                    + $p['pendientes'] + $p['incidencia_abierta'];
                if ($s->salida_fisica_at) {
                    $enTransito[$p['tarea_producto_id']] = ($enTransito[$p['tarea_producto_id']] ?? 0) + $p['pendientes'];
                }
            }
        }

        return $tarea->productos()->get()->map(fn ($p) => [
            'id' => $p->id, 'sku' => $p->sku, 'descripcion' => $p->descripcion_snapshot,
            'solicitadas' => (int) $p->cantidad_solicitada, 'encontradas' => (int) $p->cantidad_encontrada,
            'comprometidas' => $reservadas[$p->id] ?? 0,
            'en_traslado' => $enTransito[$p->id] ?? 0,
            'por_abastecer' => max(0, (int) $p->cantidad_solicitada - (int) $p->cantidad_encontrada - ($reservadas[$p->id] ?? 0)),
        ])->all();
    }

    public function productoRecibido(int $tareaProductoId): bool
    {
        foreach (SolicitudTraspasoProducto::where('tarea_producto_receptor_id', $tareaProductoId)->get() as $linea) {
            $solicitud = $linea->solicitud;
            if ($solicitud?->esAbastecimiento() && collect($this->resumen($solicitud))->contains(fn ($p) => (int) $p['id'] === (int) $linea->id && $p['buenas'] > 0)) {
                return true;
            }
        }

        return false;
    }

    public function asegurarSatisfecho(PedidoBmaTareaPreparacion $tarea): void
    {
        $solicitudes = SolicitudTraspaso::where('tarea_receptora_id', $tarea->id)->where('proposito', SolicitudTraspaso::PROPOSITO_ABASTECIMIENTO)->get();
        if ($solicitudes->isNotEmpty() && $tarea->productos()->whereColumn('cantidad_encontrada', '<', 'cantidad_solicitada')->exists()) {
            throw ValidationException::withMessages(['abastecimiento' => 'La preparación aún tiene piezas pendientes. Solicite y reciba la reposición o tramite su corrección autorizada.']);
        }
        foreach ($solicitudes as $s) {
            foreach ($this->resumen($s) as $p) {
                if ($p['pendientes'] > 0 || $p['incidencia_abierta'] > 0) {
                    throw ValidationException::withMessages(['abastecimiento' => 'Hay piezas de abastecimiento pendientes de llegada o incidencias sin resolver.']);
                }
            }
        }
    }

    private function abierta(PedidoBmaTareaPreparacion $tarea): void
    {
        if (! in_array($tarea->estado, [PedidoBmaTareaPreparacion::ESTADO_PENDIENTE, PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION, PedidoBmaTareaPreparacion::ESTADO_CON_INCIDENCIA], true)) {
            throw ValidationException::withMessages(['tarea' => 'La preparación receptora ya no admite abastecimiento.']);
        }
    }

    public function crear(PedidoBmaTareaPreparacion $tarea, User $actor, int $origenId, array $lineas, string $clave, int $version): SolicitudTraspaso
    {
        return DB::transaction(function () use ($tarea, $actor, $origenId, $lineas, $clave, $version) {
            $tarea = PedidoBmaTareaPreparacion::lockForUpdate()->findOrFail($tarea->id);
            $this->autorizar($actor, $tarea->almacen, 'control_pedidos.tienda.responder');
            $replay = TraspasoOperacion::where('idempotency_key', $clave)->first();
            if ($replay) {
                $s = SolicitudTraspaso::findOrFail($replay->solicitud_traspaso_id);
                abort_unless($replay->tipo === 'CREACION' && (int) $s->tarea_receptora_id === (int) $tarea->id, 422);

                return $s;
            }
            $this->abierta($tarea);
            abort_unless((int) $tarea->asignada_a_id === (int) $actor->id, 403);
            $this->version($tarea->version, $version);
            $destino = $tarea->almacen;
            $origen = Almacen::where('activo', true)->where('visible_en_traspasos', true)->findOrFail($origenId);
            if (! AlmacenPdv::esAlmacen($destino) || ! $destino->sucursal_id || $destino->id === $origen->id
                || ($origen->sucursal_id && (int) $origen->sucursal_id === (int) $destino->sucursal_id)
                || (AlmacenPdv::esAlmacen($origen) && ! $origen->sucursal_id)) {
                throw ValidationException::withMessages(['almacen_origen_id' => 'Elija un origen físico distinto y un destino PDV con sucursal vinculada.']);
            }
            $pendientes = collect($this->pendientes($tarea))->keyBy('id');
            if ($lineas === [] || count($lineas) !== count(array_unique(array_column($lineas, 'id')))) {
                throw ValidationException::withMessages(['lineas' => 'Seleccione productos pendientes sin duplicar.']);
            }
            $solicitud = SolicitudTraspaso::create([
                'folio' => SolicitudTraspaso::generarFolio(), 'proposito' => SolicitudTraspaso::PROPOSITO_ABASTECIMIENTO,
                'tarea_receptora_id' => $tarea->id, 'pedido_receptor_id' => $tarea->pedido_bma_id,
                'almacen_destino_id' => $destino->id, 'almacen_origen_id' => $origen->id,
                'sucursal_solicitante_id' => $destino->sucursal_id, 'vendedor_id' => $actor->id,
                'cliente_id' => $tarea->pedido->cliente_id, 'catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Pendiente'),
                'fecha_entrega_estimada' => now()->toDateString(), 'total_piezas' => 0, 'version_operativa' => 1,
            ]);
            $total = 0;
            foreach ($lineas as $linea) {
                $p = $tarea->productos()->find($linea['id']);
                $cantidad = (int) $linea['cantidad'];
                if (! $p || ! $p->producto_id || $cantidad < 1 || $cantidad > ($pendientes->get($p->id)['por_abastecer'] ?? 0)
                    || ! DB::table('inventarios')->where('almacen_id', $origen->id)->where('producto_id', $p->producto_id)->exists()) {
                    throw ValidationException::withMessages(['lineas' => 'Producto ajeno, cantidad superior al pendiente o producto fuera del catálogo del origen.']);
                }
                SolicitudTraspasoProducto::create(['solicitud_traspaso_id' => $solicitud->id, 'pedido_bma_origen_id' => $tarea->pedido_bma_id,
                    'tarea_producto_receptor_id' => $p->id, 'producto_id' => $p->producto_id,
                    'sku' => $p->sku, 'descripcion' => $p->descripcion_snapshot, 'piezas' => $cantidad]);
                $total += $cantidad;
            }
            $solicitud->update(['total_piezas' => $total]);
            TraspasoOperacion::create(['solicitud_traspaso_id' => $solicitud->id, 'tipo' => 'CREACION', 'idempotency_key' => $clave, 'actor_id' => $actor->id, 'lineas' => $lineas]);
            $this->auditar($solicitud, $actor, 'Abastecimiento solicitado para preparación existente.');
            $tarea->increment('version');

            return $solicitud;
        });
    }

    public function operar(SolicitudTraspaso $solicitud, User $actor, string $tipo, array $lineas, string $clave, int $version, ?UploadedFile $evidencia, ?string $observacion = null, array $revisiones = []): SolicitudTraspaso
    {
        $path = null;
        try {
            return DB::transaction(function () use ($solicitud, $actor, $tipo, $lineas, $clave, $version, $evidencia, $observacion, $revisiones, &$path) {
                // Siempre tarea -> solicitud: serializa creación, llegada y cierre de preparación.
                $tarea = PedidoBmaTareaPreparacion::lockForUpdate()->findOrFail($solicitud->tarea_receptora_id);
                $s = SolicitudTraspaso::lockForUpdate()->findOrFail($solicitud->id);
                abort_unless($s->esAbastecimiento() && in_array($tipo, ['SALIDA', 'LLEGADA', 'FALTANTE', 'RECHAZO_AUTORIZADO'], true), 422);
                $almacen = $tipo === 'SALIDA' ? $s->almacenOrigen : $s->almacenDestino;
                $permiso = $tipo === 'SALIDA' ? 'traspasos.responder' : ($tipo === 'RECHAZO_AUTORIZADO' ? 'control_pedidos.preparacion.corregir' : 'control_pedidos.tienda.responder');
                $this->autorizar($actor, $almacen, $permiso);
                if (in_array($tipo, ['LLEGADA', 'FALTANTE'], true)) {
                    abort_unless((int) $tarea->asignada_a_id === (int) $actor->id, 403);
                }
                $replay = TraspasoOperacion::where('idempotency_key', $clave)->first();
                if ($replay) {
                    abort_unless((int) $replay->solicitud_traspaso_id === (int) $s->id && $replay->tipo === $tipo, 422);

                    return $s;
                }
                $this->abierta($tarea);
                $this->version($s->version_operativa, $version);
                if (! $evidencia || ! $evidencia->isValid()) {
                    throw ValidationException::withMessages(['evidencia' => 'Adjunte evidencia física de esta operación.']);
                }
                if ($tipo === 'SALIDA') {
                    if ($s->salida_fisica_at || (int) $s->catalogo_estado_solicitud_id !== CatalogoEstadoSolicitud::idDe('Pendiente')) {
                        abort(422, 'La salida ya fue confirmada.');
                    }
                    $normalizadas = app(GuardarRevisionesTraspasoService::class)->normalizarRevisiones($s, $revisiones);
                    if (collect($normalizadas)->contains(fn ($r) => $r['estado_fisico'] !== 'bueno')) {
                        throw ValidationException::withMessages(['revisiones' => 'Resuelva las incidencias antes de confirmar salida.']);
                    }
                    app(GuardarRevisionesTraspasoService::class)->persistir($s, 'origen', $normalizadas, $actor);
                    $s->update(['catalogo_estado_solicitud_id' => CatalogoEstadoSolicitud::idDe('Respondida'), 'salida_fisica_at' => now(), 'respondida_por_id' => $actor->id, 'respondida_at' => now(), 'estado_fisico_general_origen' => 'bueno']);
                } else {
                    if (! $s->salida_fisica_at || (int) $s->catalogo_estado_solicitud_id !== CatalogoEstadoSolicitud::idDe('Respondida')) {
                        abort(422, 'No hay un traslado en tránsito.');
                    }
                    $resumen = collect($this->resumen($s))->keyBy('id');
                    if ($lineas === [] || count($lineas) !== count(array_unique(array_column($lineas, 'id')))) {
                        throw ValidationException::withMessages(['lineas' => 'Seleccione líneas de esta llegada sin duplicar.']);
                    }
                    foreach ($lineas as $linea) {
                        $p = $resumen->get($linea['id']);
                        $buenas = (int) ($linea['buenas'] ?? 0);
                        $danadas = (int) ($linea['danadas'] ?? 0);
                        $cantidad = (int) ($linea['cantidad'] ?? 0);
                        if (! $p || ($tipo === 'LLEGADA' && ($buenas < 0 || $danadas < 0 || $buenas + $danadas < 1 || $buenas + $danadas > $p['pendientes']))
                            || ($tipo === 'RECHAZO_AUTORIZADO' && ($cantidad < 1 || $cantidad > $p['incidencia_abierta']))
                            || ($tipo === 'FALTANTE' && ($cantidad < 1 || $cantidad > $p['pendientes'] - $p['faltantes_reportados']))) {
                            throw ValidationException::withMessages(['lineas' => 'Cantidad inválida, ya recibida o superior al pendiente.']);
                        }
                        if (($danadas > 0 || in_array($tipo, ['FALTANTE', 'RECHAZO_AUTORIZADO'], true)) && mb_strlen(trim((string) $observacion)) < 8) {
                            throw ValidationException::withMessages(['observacion' => 'Documente el daño o la autorización de rechazo (mínimo 8 caracteres).']);
                        }
                        if ($tipo === 'LLEGADA' && $buenas > 0) {
                            $producto = $tarea->productos()->findOrFail($p['tarea_producto_id']);
                            $lineaTraslado = $s->productos->firstWhere('id', $p['id']);
                            if ((int) $producto->producto_id !== (int) $lineaTraslado->producto_id) {
                                throw ValidationException::withMessages(['lineas' => 'La identidad del producto receptor cambió; solicite revisión autorizada.']);
                            }
                            if ((int) $producto->cantidad_encontrada + $buenas > (int) $producto->cantidad_solicitada) {
                                throw ValidationException::withMessages(['lineas' => 'La preparación cambió; revise sus cantidades antes de recibir.']);
                            }
                            $producto->increment('cantidad_encontrada', $buenas);
                            if (in_array($producto->estado_fisico, [null, 'sin_existencia'], true)) {
                                $producto->update(['estado_fisico' => 'bueno']);
                            }
                        }
                    }
                }
                if ($tipo === 'LLEGADA') {
                    $lineas = array_map(fn ($l) => ['id' => (int) $l['id'], 'buenas' => (int) ($l['buenas'] ?? 0), 'danadas' => (int) ($l['danadas'] ?? 0)], $lineas);
                }
                $path = $evidencia->store("traspasos/abastecimiento/{$s->id}", 'public');
                TraspasoOperacion::create(['solicitud_traspaso_id' => $s->id, 'tipo' => $tipo, 'idempotency_key' => $clave, 'actor_id' => $actor->id, 'lineas' => $lineas, 'observacion' => $observacion, 'evidencia_path' => $path]);
                $s->unsetRelation('operaciones');
                if ($tipo !== 'SALIDA' && collect($this->resumen($s))->every(fn ($p) => $p['pendientes'] === 0 && $p['incidencia_abierta'] === 0)) {
                    $s->catalogo_estado_solicitud_id = CatalogoEstadoSolicitud::idDe('Verificada');
                }
                $s->version_operativa++;
                $s->save();
                $tarea->increment('version');
                if ($tipo === 'LLEGADA') {
                    $tarea->cumplimientoFisico()->increment('version');
                }
                $this->auditar($s, $actor, $tipo.' de abastecimiento. Inventario sin sincronización.');

                return $s->fresh();
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }
    }

    private function version(int $actual, int $esperada): void
    {
        if ($actual !== $esperada) {
            throw ValidationException::withMessages(['version' => 'Otra persona modificó la operación. Actualice antes de continuar.']);
        }
    }

    private function auditar(SolicitudTraspaso $s, User $actor, string $motivo): void
    {
        AuditoriaSolicitudTraspaso::create(['solicitud_traspaso_id' => $s->id, 'usuario_id' => $actor->id,
            'estado_nuevo_id' => $s->catalogo_estado_solicitud_id, 'motivo_reporte' => $motivo,
            'datos_snapshot' => ['proposito' => $s->proposito, 'pedido_receptor_id' => $s->pedido_receptor_id, 'tarea_receptora_id' => $s->tarea_receptora_id, 'inventario_sincronizado' => false]]);
    }
}
