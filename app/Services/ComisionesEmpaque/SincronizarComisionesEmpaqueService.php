<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\ConfiguracionComisionEmpaque;
use App\Models\ComisionesEmpaque\SincronizacionComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadLineaComisionEmpaque;
use App\Models\ControlPedidos\OperacionEmpaqueMiembro;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\User;
use App\Support\ComisionesEmpaque\FuentesComisionEmpaque;
use App\Support\ComisionesEmpaque\HuellaFuenteComisionEmpaque;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SincronizarComisionesEmpaqueService
{
    public function __construct(
        private ValorarUnidadComisionEmpaque $valorar,
        private GestionarUnidadComisionEmpaqueService $gestionar,
    ) {}

    public function ejecutar(?User $responsable): SincronizacionComisionEmpaque
    {
        $config = ConfiguracionComisionEmpaque::actual();
        $sync = SincronizacionComisionEmpaque::query()->create([
            'estado' => 'en_curso',
            'iniciado_at' => now(),
            'ejecutado_por_id' => $responsable?->id,
        ]);

        if ($config->fecha_inicio === null) {
            $sync->update([
                'estado' => 'omitida',
                'mensaje' => 'Defina el inicio del periodo en Configuración. Los empaques anteriores a esa fecha no se importan: pudieron pagarse fuera del sistema.',
                'finalizado_at' => now(),
            ]);

            return $sync;
        }

        $inicio = Carbon::parse($config->fecha_inicio)->startOfDay();
        $fin = $config->fecha_fin ? Carbon::parse($config->fecha_fin)->endOfDay() : null;
        $cedis = AreaComisionEmpaque::query()->where('codigo', 'cedis')->firstOrFail();
        $tienda = AreaComisionEmpaque::query()->where('codigo', 'tienda')->firstOrFail();
        $nuevas = 0;
        $actualizadas = 0;
        $diferencias = 0;
        $detalle = [];

        // ponytail: cada actualización recorre el rango vigente, no todo el histórico.
        // El siguiente paso, si el rango crece, es una marca de agua por updated_at.
        PedidoBma::query()
            ->whereNotNull('empacado_at')
            ->where('empacado_at', '>=', $inicio)
            ->when($fin, fn ($q) => $q->where('empacado_at', '<=', $fin))
            ->with(['cajas', 'bultosEmpaque', 'cliente:id,nombre', 'paqueteria:id,nombre,categoria,modalidad_tarifa', 'tipoGuia:id,nombre', 'envioTienda:id,nombre', 'empacadoPor:id,name', 'miembroOperacionEmpaque.operacion:id,folio_operacion'])
            ->orderBy('id')
            ->chunkById(80, function ($pedidos) use ($cedis, $inicio, $responsable, &$nuevas, &$actualizadas, &$diferencias, &$detalle) {
                $ops = $this->contextoOperaciones($pedidos->pluck('id')->all());
                foreach ($pedidos as $pedido) {
                    $conteo = $this->sincronizarCedis($pedido, $cedis->id, $ops, $inicio, $responsable);
                    $nuevas += $conteo['nuevas'];
                    $actualizadas += $conteo['actualizadas'];
                    $diferencias += $conteo['diferencias'];
                    foreach ($conteo['detalle'] as $linea) {
                        if (count($detalle) < 40) {
                            $detalle[] = $linea;
                        }
                    }
                }
            });

        PedidoBmaCumplimientoFisico::query()
            ->whereNotNull('empacado_at')
            ->where('empacado_at', '>=', $inicio)
            ->when($fin, fn ($q) => $q->where('empacado_at', '<=', $fin))
            ->with(['tarea.pedido.cliente:id,nombre', 'tarea.pedido.paqueteria:id,nombre,categoria,modalidad_tarifa', 'tarea.modalidad:id,nombre'])
            ->orderBy('id')
            ->chunkById(80, function ($filas) use ($tienda, $inicio, $responsable, &$nuevas, &$actualizadas, &$diferencias, &$detalle) {
                foreach ($filas as $cumplimiento) {
                    $conteo = $this->sincronizarTienda($cumplimiento, $tienda->id, $inicio, $responsable);
                    $nuevas += $conteo['nuevas'];
                    $actualizadas += $conteo['actualizadas'];
                    $diferencias += $conteo['diferencias'];
                    foreach ($conteo['detalle'] as $linea) {
                        if (count($detalle) < 40) {
                            $detalle[] = $linea;
                        }
                    }
                }
            });

        $this->marcarFuentesAusentes($inicio, $responsable, $diferencias, $detalle);
        $this->revisarCancelacionesManuales($responsable, $diferencias, $detalle);
        $this->marcarExcepcionResguardo();

        $sync->update([
            'estado' => 'ok',
            'mensaje' => 'Sincronización terminada.',
            'unidades_nuevas' => $nuevas,
            'unidades_actualizadas' => $actualizadas,
            'diferencias' => $diferencias,
            'detalle' => $detalle,
            'finalizado_at' => now(),
        ]);

        return $sync->fresh();
    }

    /**
     * @param  list<int>  $pedidoIds
     * @return array<int, array{hay_cajas: bool, folio: ?string}>
     */
    private function contextoOperaciones(array $pedidoIds): array
    {
        $miembros = OperacionEmpaqueMiembro::query()
            ->with(['operacion:id,folio_operacion', 'pedido.cajas'])
            ->whereIn('pedido_bma_id', $pedidoIds)
            ->get();

        $ops = [];
        foreach ($miembros as $miembro) {
            $opId = (int) $miembro->operacion_empaque_id;
            if (isset($ops[$opId])) {
                continue;
            }
            $todos = OperacionEmpaqueMiembro::query()
                ->with('pedido.cajas')
                ->where('operacion_empaque_id', $opId)
                ->get();
            $hayCajas = $todos->contains(fn (OperacionEmpaqueMiembro $m) => $m->pedido && $m->pedido->cajas->contains(fn (PedidoBmaCaja $c) => $c->estaActiva()));
            $ops[$opId] = [
                'hay_cajas' => $hayCajas,
                'folio' => $miembro->operacion?->folio_operacion,
            ];
        }

        $porPedido = [];
        foreach ($miembros as $miembro) {
            $porPedido[(int) $miembro->pedido_bma_id] = $ops[(int) $miembro->operacion_empaque_id];
        }

        return $porPedido;
    }

    /** @return array{nuevas: int, actualizadas: int, diferencias: int, detalle: list<string>} */
    private function sincronizarCedis(PedidoBma $pedido, int $areaId, array $ops, Carbon $inicio, ?User $responsable): array
    {
        $op = $ops[(int) $pedido->id] ?? null;
        $propuestas = $this->propuestasCedis($pedido, $areaId, $op);
        $claves = [];
        $conteo = ['nuevas' => 0, 'actualizadas' => 0, 'diferencias' => 0, 'detalle' => []];

        foreach ($propuestas as $propuesta) {
            $claves[] = $propuesta['clave_origen'];
            $resultado = $this->guardar($propuesta, $inicio, $responsable, $pedido);
            $conteo['nuevas'] += $resultado['nueva'] ? 1 : 0;
            $conteo['actualizadas'] += $resultado['actualizada'] ? 1 : 0;
            $conteo['diferencias'] += $resultado['diferencia'] ? 1 : 0;
            if ($resultado['mensaje']) {
                $conteo['detalle'][] = $resultado['mensaje'];
            }
        }

        $this->ausentesDelPedido($pedido->id, FuentesComisionEmpaque::CEDIS_CAJA, $claves, $responsable, $conteo);
        $this->ausentesDelPedido($pedido->id, FuentesComisionEmpaque::CEDIS_BULTO, $claves, $responsable, $conteo);
        $this->ausentesDelPedido($pedido->id, FuentesComisionEmpaque::CEDIS_CABECERA, $claves, $responsable, $conteo);

        return $conteo;
    }

    /** @param  array{hay_cajas: bool, folio: ?string}|null  $op */
    private function propuestasCedis(PedidoBma $pedido, int $areaId, ?array $op): array
    {
        $base = $this->basePedido($pedido, $areaId, FuentesComisionEmpaque::CEDIS_CAJA);
        $base['operacion_folio'] = $op['folio'] ?? null;
        $activas = $pedido->cajas->filter(fn (PedidoBmaCaja $c) => $c->estaActiva());

        if ($activas->isNotEmpty()) {
            return $activas->map(function (PedidoBmaCaja $caja) use ($base) {
                return array_merge($base, [
                    'clave_origen' => FuentesComisionEmpaque::CEDIS_CAJA.':'.$caja->id,
                    'procedencia' => FuentesComisionEmpaque::CEDIS_CAJA,
                    'origen_tipo' => 'pedido_bma_cajas',
                    'origen_id' => $caja->id,
                    'cantidad_origen' => max(1, (int) $caja->cantidad),
                    'situacion_inicial' => null,
                    'linea' => [
                        'catalogo_tipo_caja_id' => $caja->catalogo_tipo_caja_id,
                        'cantidad' => max(1, (int) $caja->cantidad),
                        'es_bolsa' => false,
                    ],
                ]);
            })->all();
        }

        if ($pedido->bultosEmpaque->isNotEmpty()) {
            $solape = (bool) ($op['hay_cajas'] ?? false);

            return $pedido->bultosEmpaque->map(function ($bulto) use ($base, $solape) {
                return array_merge($base, [
                    'clave_origen' => FuentesComisionEmpaque::CEDIS_BULTO.':'.$bulto->id,
                    'procedencia' => FuentesComisionEmpaque::CEDIS_BULTO,
                    'origen_tipo' => 'pedido_bma_bultos_empaque',
                    'origen_id' => $bulto->id,
                    'cantidad_origen' => 1,
                    'situacion_inicial' => $solape ? UnidadComisionEmpaque::SOLAPE_REVISION : null,
                    'linea' => null,
                    'empacador_user_id' => $bulto->empacado_por_id ?: $base['empacador_user_id'],
                ]);
            })->all();
        }

        if (($op['hay_cajas'] ?? false) || (int) $pedido->numero_cajas < 1) {
            return [];
        }

        return [array_merge($base, [
            'clave_origen' => FuentesComisionEmpaque::CEDIS_CABECERA.':'.$pedido->id,
            'procedencia' => FuentesComisionEmpaque::CEDIS_CABECERA,
            'origen_tipo' => 'pedidos_bma',
            'origen_id' => $pedido->id,
            'cantidad_origen' => (int) $pedido->numero_cajas,
            'situacion_inicial' => null,
            'linea' => null,
        ])];
    }

    /** @return array{nuevas: int, actualizadas: int, diferencias: int, detalle: list<string>} */
    private function sincronizarTienda(PedidoBmaCumplimientoFisico $cumplimiento, int $areaId, Carbon $inicio, ?User $responsable): array
    {
        $pedido = $cumplimiento->tarea?->pedido;
        if (! $pedido || (int) $cumplimiento->bultos_salida < 1) {
            return ['nuevas' => 0, 'actualizadas' => 0, 'diferencias' => 0, 'detalle' => ['Cumplimiento '.$cumplimiento->id.' sin pedido o sin bultos de salida.']];
        }

        $propuesta = array_merge($this->basePedido($pedido, $areaId, FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO), [
            'clave_origen' => FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO.':'.$cumplimiento->id,
            'procedencia' => FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO,
            'origen_tipo' => 'pedido_bma_cumplimiento_fisico',
            'origen_id' => $cumplimiento->id,
            'empacado_at' => $cumplimiento->empacado_at,
            'empacador_user_id' => $cumplimiento->empacado_por_id,
            'empacador_nombre' => $cumplimiento->empacado_por_id
                ? (DB::table('users')->where('id', $cumplimiento->empacado_por_id)->value('name') ?: null)
                : null,
            'cantidad_origen' => (int) $cumplimiento->bultos_salida,
            'situacion_inicial' => null,
            'linea' => null,
            'logistica' => array_merge($this->logistica($pedido), [
                'modalidad_preparacion' => $cumplimiento->tarea?->modalidad?->nombre,
                'bultos_salida' => (int) $cumplimiento->bultos_salida,
            ]),
        ]);

        $resultado = $this->guardar($propuesta, $inicio, $responsable, $pedido);

        return [
            'nuevas' => $resultado['nueva'] ? 1 : 0,
            'actualizadas' => $resultado['actualizada'] ? 1 : 0,
            'diferencias' => $resultado['diferencia'] ? 1 : 0,
            'detalle' => $resultado['mensaje'] ? [$resultado['mensaje']] : [],
        ];
    }

    /** @return array<string, mixed> */
    private function basePedido(PedidoBma $pedido, int $areaId, string $procedencia): array
    {
        return [
            'area_id' => $areaId,
            'pedido_bma_id' => $pedido->id,
            'procedencia' => $procedencia,
            'origen_registro' => 'sincronizado',
            'empacado_at' => $pedido->empacado_at,
            'empacador_user_id' => $pedido->empacado_por_id,
            'empacador_nombre' => $pedido->empacadoPor?->name,
            'cliente_nombre' => $pedido->cliente?->nombre,
            'folio_pedido' => $pedido->folio,
            'folio_remision' => $pedido->folio_remision,
            'logistica' => $this->logistica($pedido),
            'es_complemento' => $pedido->esComplemento(),
            'operacion_folio' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function logistica(PedidoBma $pedido): array
    {
        return [
            'paqueteria' => $pedido->paqueteria?->nombre,
            'categoria_paqueteria' => $pedido->paqueteria?->categoria,
            'modalidad_tarifa' => $pedido->paqueteria?->modalidad_tarifa,
            'tipo_guia' => $pedido->tipoGuia?->nombre,
            'envio_tienda' => $pedido->envioTienda?->nombre,
            'origen_solicitud' => $pedido->origen_solicitud,
        ];
    }

    /**
     * @param  array<string, mixed>  $propuesta
     * @return array{nueva: bool, actualizada: bool, diferencia: bool, mensaje: ?string}
     */
    private function guardar(array $propuesta, Carbon $inicio, ?User $responsable, PedidoBma $pedido): array
    {
        $huella = HuellaFuenteComisionEmpaque::de(
            $propuesta['cantidad_origen'],
            $propuesta['empacado_at'],
            $pedido->cancelado_at,
            $propuesta['linea']['catalogo_tipo_caja_id'] ?? null
        );

        $unidad = UnidadComisionEmpaque::query()->where('clave_origen', $propuesta['clave_origen'])->first();
        $reempaque = PedidoBmaHistorialEstado::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('accion', AccionesHistorialPedidoBma::REVERTIR_EMPAQUE)
            ->exists();

        if (! $unidad) {
            if ($this->gestionar->cubiertaPorConstancia((int) $propuesta['area_id'], $propuesta['folio_remision'] ?? null, $propuesta['empacado_at'])) {
                return ['nueva' => false, 'actualizada' => false, 'diferencia' => false, 'mensaje' => 'La remisión ya está como constancia pagada fuera.'];
            }
            $unidad = UnidadComisionEmpaque::query()->create([
                'clave_origen' => $propuesta['clave_origen'],
                'area_id' => $propuesta['area_id'],
                'pedido_bma_id' => $propuesta['pedido_bma_id'],
                'procedencia' => $propuesta['procedencia'],
                'origen_registro' => 'sincronizado',
                'origen_tipo' => $propuesta['origen_tipo'],
                'origen_id' => $propuesta['origen_id'],
                'empacado_at' => $propuesta['empacado_at'],
                'incorporado_at' => now(),
                'empacador_user_id' => $propuesta['empacador_user_id'],
                'empacador_nombre' => $propuesta['empacador_nombre'],
                'cliente_nombre' => $propuesta['cliente_nombre'],
                'folio_pedido' => $propuesta['folio_pedido'],
                'folio_remision' => $propuesta['folio_remision'],
                'logistica' => $propuesta['logistica'],
                'cantidad_origen' => $propuesta['cantidad_origen'],
                'situacion' => $propuesta['situacion_inicial']
                    ?: ($reempaque ? UnidadComisionEmpaque::REEMPAQUE_REVISION : UnidadComisionEmpaque::PENDIENTE_CLASIFICACION),
                'revision_estado' => 'no_aplica',
                'huella' => $huella,
                'operacion_folio' => $propuesta['operacion_folio'],
                'es_complemento' => $propuesta['es_complemento'],
            ]);
            if (! empty($propuesta['linea'])) {
                UnidadLineaComisionEmpaque::query()->create([
                    'unidad_id' => $unidad->id,
                    'catalogo_tipo_caja_id' => $propuesta['linea']['catalogo_tipo_caja_id'],
                    'cantidad' => $propuesta['linea']['cantidad'],
                    'es_bolsa' => false,
                ]);
            }
            $this->valorar->marcarTardiaSiCorresponde($unidad);
            $this->valorar->aplicar($unidad->fresh('lineas'));
            $this->aplicarCancelacion($unidad->fresh(), $pedido, $responsable);
            $this->gestionar->marcarSiDuplicado($unidad->fresh());

            return ['nueva' => true, 'actualizada' => false, 'diferencia' => (bool) $unidad->incorporacion_tardia, 'mensaje' => $unidad->incorporacion_tardia ? 'Incorporación tardía '.$unidad->clave_origen : null];
        }

        return DB::transaction(function () use ($unidad, $propuesta, $huella, $reempaque, $pedido, $responsable) {
            $unidad = UnidadComisionEmpaque::query()->whereKey($unidad->id)->lockForUpdate()->first();
            if ($unidad->corte_id) {
                $diferencia = $unidad->huella !== $huella;
                if ($diferencia) {
                    $unidad->diferencia = 'La fuente cambió después del corte. El corte conserva el original.';
                    $unidad->save();
                    $this->valorar->abrirIncidencia($unidad, 'diferencia_fuente', $responsable, $unidad->diferencia, ['huella' => $huella]);
                }
                $this->aplicarCancelacion($unidad, $pedido, $responsable);

                return ['nueva' => false, 'actualizada' => false, 'diferencia' => $diferencia, 'mensaje' => $diferencia ? $unidad->diferencia : null];
            }

            return $this->actualizarAbierta($unidad, $propuesta, $huella, $reempaque, $pedido, $responsable);
        });
    }

    /** @param  array<string, mixed>  $propuesta
     * @return array{nueva: bool, actualizada: bool, diferencia: bool, mensaje: ?string}
     */
    private function actualizarAbierta(UnidadComisionEmpaque $unidad, array $propuesta, string $huella, bool $reempaque, PedidoBma $pedido, ?User $responsable): array
    {
        $huellaPrevia = (string) $unidad->huella;
        $anclaPrevia = $unidad->ancla_decision;
        $material = $huellaPrevia !== '' && $huellaPrevia !== $huella;
        $cantidadCambio = (int) $unidad->cantidad_origen !== (int) $propuesta['cantidad_origen'];
        $unidad->fill([
            'empacado_at' => $propuesta['empacado_at'],
            'empacador_user_id' => $propuesta['empacador_user_id'],
            'empacador_nombre' => $propuesta['empacador_nombre'],
            'cliente_nombre' => $propuesta['cliente_nombre'],
            'folio_pedido' => $propuesta['folio_pedido'],
            'folio_remision' => $propuesta['folio_remision'],
            'logistica' => $propuesta['logistica'],
            'cantidad_origen' => $propuesta['cantidad_origen'],
            'operacion_folio' => $propuesta['operacion_folio'],
            'huella' => $huella,
            'es_complemento' => $propuesta['es_complemento'],
        ]);

        $cubierta = ! $material && $this->valorar->anclaCubre($unidad, ['excluir', 'descartar']);
        if ($cubierta) {
            $unidad->situacion = UnidadComisionEmpaque::EXCLUIDA;
        } elseif ($propuesta['situacion_inicial'] === UnidadComisionEmpaque::SOLAPE_REVISION
            && $unidad->situacion === UnidadComisionEmpaque::ELEGIBLE) {
            $unidad->situacion = UnidadComisionEmpaque::SOLAPE_REVISION;
        }

        if (! $cubierta && $reempaque && $unidad->situacion === UnidadComisionEmpaque::REVERTIDA_REVISION) {
            $unidad->situacion = UnidadComisionEmpaque::REEMPAQUE_REVISION;
            $unidad->diferencia = 'El empaque volvió después de una reversión. Revise si es corrección o trabajo distinto.';
        }

        if ($cantidadCambio) {
            $unidad->lineas()->delete();
            $unidad->diferencia = $anclaPrevia
                ? 'Cambió la cantidad. La decisión «'.$anclaPrevia.'» queda registrada y hace falta una revisión nueva.'
                : 'Cambió la cantidad de origen. Vuelva a clasificar el desglose.';
            $unidad->situacion = $propuesta['situacion_inicial'] ?: UnidadComisionEmpaque::PENDIENTE_CLASIFICACION;
            if (! empty($propuesta['linea'])) {
                UnidadLineaComisionEmpaque::query()->create([
                    'unidad_id' => $unidad->id,
                    'catalogo_tipo_caja_id' => $propuesta['linea']['catalogo_tipo_caja_id'],
                    'cantidad' => $propuesta['linea']['cantidad'],
                    'es_bolsa' => false,
                ]);
            }
        }

        $unidad->save();
        $this->valorar->aplicar($unidad->fresh('lineas'));
        $this->aplicarCancelacion($unidad->fresh(), $pedido, $responsable);
        $unidad = $unidad->fresh();

        if ($material && $anclaPrevia && ! $unidad->corte_id && $unidad->situacion === UnidadComisionEmpaque::ELEGIBLE) {
            $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_CLASIFICACION;
            $unidad->importe_centavos = null;
            $unidad->cantidad_comisionable = null;
            $unidad->diferencia = 'Cambió el hecho revisado («'.$anclaPrevia.'»). La decisión anterior se conserva y hace falta una revisión nueva.';
            $unidad->save();
            $this->valorar->abrirIncidencia($unidad, 'cambio_material', $responsable, $unidad->diferencia, [
                'huella' => $huella,
                'decision_previa' => $anclaPrevia,
            ]);
        }

        $this->gestionar->marcarSiDuplicado($unidad->fresh());

        return [
            'nueva' => false,
            'actualizada' => true,
            'diferencia' => $material || $cantidadCambio,
            'mensaje' => ($material || $cantidadCambio) ? $unidad->clave_origen.': '.($unidad->diferencia ?: 'Fuente actualizada') : null,
        ];
    }

    private function aplicarCancelacion(UnidadComisionEmpaque $unidad, PedidoBma $pedido, ?User $responsable): void
    {
        if (! $pedido->cancelado_at) {
            return;
        }

        $datos = [
            'huella' => $unidad->huella,
            'cancelado_at' => (string) $pedido->cancelado_at,
            'decision_previa' => $unidad->ancla_decision,
        ];

        if ($unidad->corte_id) {
            $this->valorar->abrirIncidencia($unidad, 'cancelacion', $responsable, 'El pedido se canceló después de un corte confirmado. Conserve el corte y registre un ajuste si corresponde.', $datos);

            return;
        }

        if ($this->valorar->anclaCubre($unidad, ['conservar', 'excluir'])) {
            return;
        }

        if ($unidad->situacion === UnidadComisionEmpaque::RECHAZADA) {
            return;
        }
        if ($unidad->situacion === UnidadComisionEmpaque::EXCLUIDA && ! $unidad->ancla_decision) {
            return;
        }

        $unidad->situacion = UnidadComisionEmpaque::PENDIENTE_DECISION;
        if ($unidad->ancla_decision) {
            $unidad->diferencia = 'La cancelación ya tenía una decisión («'.$unidad->ancla_decision.'») sobre otro hecho. Revise el cambio nuevo.';
        }
        $unidad->save();
        $this->valorar->abrirIncidencia($unidad, 'cancelacion', $responsable, 'Pedido cancelado. Falta la decisión de conservar o excluir la comisión.', $datos);
    }

    /** @param  list<string>  $claves */
    private function ausentesDelPedido(int $pedidoId, string $procedencia, array $claves, ?User $responsable, array &$conteo): void
    {
        $ausentes = UnidadComisionEmpaque::query()
            ->where('pedido_bma_id', $pedidoId)
            ->where('procedencia', $procedencia)
            ->where('origen_registro', 'sincronizado')
            ->when($claves !== [], fn ($q) => $q->whereNotIn('clave_origen', $claves))
            ->get();

        foreach ($ausentes as $unidad) {
            if ($this->conservarDecisionAnteAusencia($unidad)) {
                continue;
            }
            if ($unidad->corte_id) {
                $this->valorar->abrirIncidencia($unidad, 'reversion', $responsable, 'La fuente ya no está activa y la unidad pertenece a un corte.', [
                    'huella' => $unidad->huella,
                ]);
                $conteo['diferencias']++;
                continue;
            }
            $ya = $unidad->situacion === UnidadComisionEmpaque::REVERTIDA_REVISION;
            $unidad->situacion = UnidadComisionEmpaque::REVERTIDA_REVISION;
            $unidad->diferencia = 'La caja, el bulto o la cabecera ya no está en la fuente.';
            $unidad->importe_centavos = null;
            $unidad->save();
            $this->valorar->abrirIncidencia($unidad, 'reversion', $responsable, $unidad->diferencia, ['huella' => $unidad->huella]);
            if (! $ya) {
                $conteo['diferencias']++;
            }
        }
    }

    /** @param  list<string>  $detalle */
    private function marcarFuentesAusentes(Carbon $inicio, ?User $responsable, int &$diferencias, array &$detalle): void
    {
        UnidadComisionEmpaque::query()
            ->where('origen_registro', 'sincronizado')
            ->where('empacado_at', '>=', $inicio)
            ->whereNotNull('pedido_bma_id')
            ->whereIn('procedencia', [FuentesComisionEmpaque::CEDIS_CAJA, FuentesComisionEmpaque::CEDIS_BULTO, FuentesComisionEmpaque::CEDIS_CABECERA])
            ->orderBy('id')
            ->chunkById(100, function ($unidades) use ($responsable, &$diferencias, &$detalle) {
                $pedidos = PedidoBma::query()->whereIn('id', $unidades->pluck('pedido_bma_id'))->get()->keyBy('id');
                foreach ($unidades as $unidad) {
                    $pedido = $pedidos->get($unidad->pedido_bma_id);
                    if ($pedido && $pedido->empacado_at !== null) {
                        continue;
                    }
                    if ($this->conservarDecisionAnteAusencia($unidad)) {
                        continue;
                    }
                    $ya = $unidad->situacion === UnidadComisionEmpaque::REVERTIDA_REVISION;
                    if ($unidad->corte_id) {
                        $this->valorar->abrirIncidencia($unidad, 'reversion', $responsable, 'Se revirtió el empaque de un pedido ya incluido en un corte.', [
                            'huella' => $unidad->huella,
                        ]);
                    } else {
                        $unidad->situacion = UnidadComisionEmpaque::REVERTIDA_REVISION;
                        $unidad->diferencia = 'El pedido ya no tiene fecha de empaque.';
                        $unidad->save();
                        $this->valorar->abrirIncidencia($unidad, 'reversion', $responsable, $unidad->diferencia, ['huella' => $unidad->huella]);
                    }
                    if ($ya) {
                        continue;
                    }
                    $diferencias++;
                    if (count($detalle) < 40) {
                        $detalle[] = 'Reversión de empaque en '.$unidad->clave_origen;
                    }
                }
            });
    }

    /** @param  list<string>  $detalle */
    private function revisarCancelacionesManuales(?User $responsable, int &$diferencias, array &$detalle): void
    {
        UnidadComisionEmpaque::query()
            ->where('origen_registro', 'manual')
            ->whereNotNull('pedido_bma_id')
            ->orderBy('id')
            ->chunkById(100, function ($unidades) use ($responsable, &$diferencias, &$detalle) {
                foreach ($unidades as $unidad) {
                    $antes = $unidad->situacion;
                    $this->gestionar->reconciliarCancelacionManual($unidad, $responsable);
                    $unidad->refresh();
                    if ($unidad->situacion === $antes) {
                        continue;
                    }
                    $diferencias++;
                    if (count($detalle) < 40) {
                        $detalle[] = 'Cancelación de registro manual en '.$unidad->clave_origen;
                    }
                }
            });
    }

    private function conservarDecisionAnteAusencia(UnidadComisionEmpaque $unidad): bool
    {
        if (! $this->valorar->anclaCubre($unidad, ['aceptar', 'descartar', 'excluir', 'conservar'])) {
            return false;
        }
        if (! $unidad->corte_id && in_array($unidad->ancla_decision, ['descartar', 'excluir'], true)) {
            $unidad->situacion = UnidadComisionEmpaque::EXCLUIDA;
            $unidad->importe_centavos = 0;
            $unidad->cantidad_comisionable = 0;
            $unidad->save();
        }

        return true;
    }

    private function marcarExcepcionResguardo(): void
    {
        $pedidoIds = UnidadComisionEmpaque::query()
            ->whereNotNull('pedido_bma_id')
            ->select('pedido_bma_id')
            ->groupBy('pedido_bma_id')
            ->havingRaw('count(distinct procedencia) > 1')
            ->pluck('pedido_bma_id');

        if ($pedidoIds->isEmpty()) {
            return;
        }

        $resguardos = PedidoBma::query()->whereIn('id', $pedidoIds)->where('es_resguardo', true)->pluck('id');
        foreach ($resguardos as $pedidoId) {
            $unidades = UnidadComisionEmpaque::query()->where('pedido_bma_id', $pedidoId)->get();
            $hayCedis = $unidades->contains(fn (UnidadComisionEmpaque $u) => str_starts_with($u->procedencia, 'cedis_'));
            $hayTienda = $unidades->contains(fn (UnidadComisionEmpaque $u) => in_array($u->procedencia, [FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO, FuentesComisionEmpaque::MANUAL], true)
                && $u->situacion !== UnidadComisionEmpaque::RECHAZADA);
            if ($hayCedis && $hayTienda) {
                UnidadComisionEmpaque::query()->where('pedido_bma_id', $pedidoId)->update(['excepcion_resguardo' => true]);
            }
        }
    }
}
