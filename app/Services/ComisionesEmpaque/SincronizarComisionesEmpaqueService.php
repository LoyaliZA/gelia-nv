<?php

namespace App\Services\ComisionesEmpaque;

use App\Models\ComisionesEmpaque\AreaComisionEmpaque;
use App\Models\ComisionesEmpaque\ConfiguracionComisionEmpaque;
use App\Models\ComisionesEmpaque\SincronizacionComisionEmpaque;
use App\Models\ComisionesEmpaque\UnidadComisionEmpaque;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\OperacionEmpaqueMiembro;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaja;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaHistorialEstado;
use App\Models\User;
use App\Support\ComisionesEmpaque\DineroComisionEmpaque;
use App\Support\ComisionesEmpaque\FuentesComisionEmpaque;
use App\Support\ComisionesEmpaque\HuellaFuenteComisionEmpaque;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use Illuminate\Support\Carbon;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SincronizarComisionesEmpaqueService
{
    public function __construct(
        private ValorarUnidadComisionEmpaque $valorar,
        private GestionarUnidadComisionEmpaqueService $gestionar,
    ) {}

    public function ejecutar(?User $responsable, ?array $areasAutorizadas = null, ?array $pedidos = null, ?array $intervalo = null, bool $automatica = false, ?int $versionRango = null): SincronizacionComisionEmpaque
    {
        $sync = SincronizacionComisionEmpaque::query()->create([
            'estado' => 'en_curso',
            'iniciado_at' => now(),
            'ejecutado_por_id' => $responsable?->id,
            'area_ids' => $areasAutorizadas ?? AreaComisionEmpaque::query()->pluck('id')->all(),
            'automatica' => $automatica,
            'version_rango' => $versionRango,
        ]);

        $conteo = $this->conteo();
        try {
            $config = ConfiguracionComisionEmpaque::actual();
            if ($config->fecha_inicio === null) {
                $sync->update([
                    'estado' => 'omitida',
                    'mensaje' => 'Defina el inicio de sincronización en Configuración.',
                    'finalizado_at' => now(),
                ]);

                return $sync;
            }

            if ($versionRango !== null && (int) $config->version_rango !== $versionRango) {
                $sync->update(['estado' => 'omitida', 'mensaje' => 'El rango cambió durante la preparación.', 'finalizado_at' => now()]);

                return $sync;
            }

            $inicio = DineroComisionEmpaque::inicioDia($config->fecha_inicio->toDateString(), $config->zona_horaria);
            $fin = $config->fecha_fin ? DineroComisionEmpaque::finDia($config->fecha_fin->toDateString(), $config->zona_horaria) : null;
            $areas = AreaComisionEmpaque::query()->whereIn('id', $sync->area_ids)->orderBy('id')->pluck('id', 'codigo');
            $ids = $pedidos !== null
                ? collect($pedidos)->unique()->sort()
                : $this->pedidosEnIntervalo($intervalo ? $inicio->max($intervalo[0]) : $inicio, $intervalo && $fin ? $fin->min($intervalo[1]) : ($intervalo[1] ?? $fin), $areas->all());
            foreach ($ids as $id) {
                $resultado = DB::transaction(function () use ($id, $areas, $inicio, $fin, $responsable, $config) {
                    $rango = ConfiguracionComisionEmpaque::query()->sharedLock()->findOrFail($config->id);
                    if ($rango->version_rango !== $config->version_rango) {
                        throw new RuntimeException('El rango cambió durante la sincronización.');
                    }

                    AreaComisionEmpaque::query()->whereIn('id', $areas->values())->orderBy('id')->lockForUpdate()->get();

                    return $this->sincronizarPedido((int) $id, $areas->all(), $inicio, $fin, $responsable);
                }, 3);
                $this->sumar($conteo, $resultado);
            }

            $sync->update([
                'estado' => 'ok',
                'mensaje' => 'Sincronización terminada.',
                'unidades_nuevas' => $conteo['nuevas'],
                'unidades_actualizadas' => $conteo['actualizadas'],
                'diferencias' => $conteo['diferencias'],
                'detalle' => $conteo['detalle'],
                'finalizado_at' => now(),
            ]);
        } catch (Throwable $error) {
            report($error);
            $sync->update([
                'estado' => 'error',
                'mensaje' => 'La actualización quedó incompleta. Vuelva a intentar.',
                'finalizado_at' => now(),
            ]);
        } finally {
            foreach ($conteo['areas_modificadas'] as $areaModificada) {
                Cache::forever('comisiones-empaque:revision:'.$areaModificada, (string) Str::uuid());
            }
        }

        return $sync;
    }

    public function pedidosEnIntervalo(Carbon $inicio, ?Carbon $fin, array $areas, ?array $ids = null): Enumerable
    {
        $fecha = fn ($q) => $q->where('empacado_at', '>=', $inicio)
            ->when($fin, fn ($q) => $q->where('empacado_at', '<=', $fin));
        $existentes = UnidadComisionEmpaque::query()->whereIn('area_id', $areas)
            ->whereNotNull('pedido_bma_id')->tap($fecha)->select('pedido_bma_id as id');
        $codigos = AreaComisionEmpaque::query()->whereIn('id', $areas)->pluck('codigo');
        $cedis = $codigos->contains('cedis');
        $tienda = $codigos->contains('tienda');
        $municipio = 'solicitud_preparacion_json->modalidad_entrega';
        $areaMunicipio = 'solicitud_preparacion_json->consulta_municipal->area';
        $pedidos = PedidoBma::query()->select('id')->where(function ($pedidos) use ($cedis, $tienda, $fecha, $municipio, $areaMunicipio) {
            $pedidos->whereRaw('1 = 0');
            if ($cedis || $tienda) {
                $pedidos->orWhere(function ($cabecera) use ($cedis, $tienda, $fecha, $municipio, $areaMunicipio) {
                    $cabecera->whereNotNull('empacado_at')->where(fn ($q) => $q->where($fecha)->orWhereHas('bultosEmpaque', $fecha));
                    if ($cedis && ! $tienda) {
                        $cabecera->where(fn ($q) => $q->whereNull($municipio)->orWhere($municipio, '!=', 'municipal')
                            ->orWhereNull($areaMunicipio)->orWhere($areaMunicipio, '!=', 'tienda'));
                    }
                    if ($tienda && ! $cedis) {
                        $cabecera->where($municipio, 'municipal')->where($areaMunicipio, 'tienda');
                    }
                });
            }
            if ($tienda) {
                $pedidos->orWhereHas('tareasPreparacion.cumplimientoFisico', $fecha);
            }
        });

        return DB::query()->fromSub($pedidos->toBase()->union($existentes->toBase()), 'pedidos')
            ->when($ids !== null, fn ($query) => $query->whereIntegerInRaw('id', $ids))
            ->lazyById(100)->pluck('id');
    }

    private function sincronizarPedido(int $id, array $areas, Carbon $inicio, ?Carbon $fin, ?User $responsable): array
    {
        $conteo = $this->conteo();
        $pedido = PedidoBma::query()->with([
            'cajas.remisionesActivas.pedido:id,numero_remision,folio_remision',
            'bultosEmpaque.empacadoPor:id,name', 'cliente:id,nombre', 'paqueteria:id,nombre,categoria,modalidad_tarifa',
            'tipoGuia:id,nombre', 'envioTienda:id,nombre', 'anexosEnvio', 'caratulasComision', 'empacadoPor:id,name', 'estatus:id,fase_ciclo',
            'tareasPreparacion.cumplimientoFisico.empacadoPor:id,name', 'tareasPreparacion.modalidad', 'tareasPreparacion.paqueteria',
        ])->find($id);

        $existentes = UnidadComisionEmpaque::query()->where('pedido_bma_id', $id)
            ->whereIn('area_id', $areas)->orderBy('id')->lockForUpdate()->get();
        $claves = [];
        $propuestas = [];
        if ($pedido) {
            $municipal = HuellaFuenteComisionEmpaque::empaqueMunicipalEnTienda($pedido);
            $area = $areas[$municipal ? 'tienda' : 'cedis'] ?? null;
            if ($area && $pedido->empacado_at) {
                $propuestas = $this->propuestasCedis($pedido, $area, $this->contextoOperaciones([$id])[$id] ?? null);
                if ($municipal) {
                    $propuestas = $this->propuestasMunicipalTienda($pedido, $area, $propuestas);
                }
            }
            if (isset($areas['tienda'])) {
                foreach ($pedido->tareasPreparacion as $tarea) {
                    $cumplimiento = $tarea->cumplimientoFisico;
                    if ($cumplimiento && $cumplimiento->empacado_at && $cumplimiento->bultos_salida > 0) {
                        $cumplimiento->setRelation('tarea', $tarea->setRelation('pedido', $pedido));
                        $propuestas[] = $this->propuestaTienda($cumplimiento, $areas['tienda']);
                    }
                }
            }
        }
        foreach ($propuestas as $propuesta) {
            if ($propuesta['empacado_at']->lt($inicio) || ($fin && $propuesta['empacado_at']->gt($fin))) {
                continue;
            }
            $claves[] = $propuesta['clave_origen'];
        }
        foreach ($existentes as $unidad) {
            $antes = $conteo['diferencias'];
            if (in_array($unidad->origen_registro, ['manual', 'carga'], true)) {
                $situacionPrevia = $unidad->situacion;
                $this->gestionar->reconciliarCancelacionManual($unidad, $responsable);
                $conteo['diferencias'] += (int) ($situacionPrevia !== $unidad->situacion);
            } elseif (! in_array($unidad->clave_origen, $claves, true)
                && $unidad->empacado_at->gte($inicio) && (! $fin || $unidad->empacado_at->lte($fin))) {
                $this->marcarAusente($unidad, $responsable, $conteo);
            }
            if ($antes !== $conteo['diferencias']) {
                $conteo['areas_modificadas'][] = $unidad->area_id;
            }
        }
        foreach ($propuestas as $propuesta) {
            if (! in_array($propuesta['clave_origen'], $claves, true)) {
                continue;
            }
            $resultado = $this->guardar($propuesta, $responsable, $pedido, $claves);
            $this->sumar($conteo, [
                'nuevas' => (int) $resultado['nueva'],
                'actualizadas' => (int) $resultado['actualizada'],
                'diferencias' => (int) $resultado['diferencia'],
                'detalle' => $resultado['mensaje'] ? [$resultado['mensaje']] : [],
                'areas_modificadas' => $resultado['nueva'] || $resultado['actualizada'] ? [$propuesta['area_id']] : [],
            ]);
        }
        $this->marcarExcepcionResguardo(array_values($areas), $id);

        return $conteo;
    }

    private function conteo(): array
    {
        return ['nuevas' => 0, 'actualizadas' => 0, 'diferencias' => 0, 'detalle' => [], 'areas_modificadas' => []];
    }

    private function sumar(array &$conteo, array $resultado): void
    {
        foreach (['nuevas', 'actualizadas', 'diferencias'] as $campo) {
            $conteo[$campo] += $resultado[$campo];
        }
        $conteo['areas_modificadas'] = array_unique(array_merge($conteo['areas_modificadas'], $resultado['areas_modificadas']));
        $conteo['detalle'] = array_slice(array_merge($conteo['detalle'], $resultado['detalle']), 0, 40);
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
                'consolidada' => $todos->count() > 1,
                'folio' => $miembro->operacion?->folio_operacion,
            ];
        }

        $porPedido = [];
        foreach ($miembros as $miembro) {
            $porPedido[(int) $miembro->pedido_bma_id] = $ops[(int) $miembro->operacion_empaque_id];
        }

        return $porPedido;
    }

    private function propuestasMunicipalTienda(PedidoBma $pedido, int $areaId, array $propuestas): array
    {
        if ($pedido->tareasPreparacion()->whereHas('cumplimientoFisico', fn ($q) => $q->whereNotNull('empacado_at')->where('bultos_salida', '>', 0))->exists()) {
            return [];
        }

        if ($propuestas === []) {
            $propuestas[] = $this->basePedido($pedido, $areaId, FuentesComisionEmpaque::TIENDA_MUNICIPAL) + [
                'clave_origen' => 'pedido:'.$pedido->id,
                'origen_tipo' => 'pedidos_bma',
                'origen_id' => $pedido->id,
                'cantidad_origen' => 0,
                'situacion_inicial' => null,
                'linea' => null,
            ];
        }

        $cedisLiquidado = UnidadComisionEmpaque::query()->where('pedido_bma_id', $pedido->id)
            ->where('procedencia', 'like', 'cedis_%')->whereNotNull('corte_id')->exists();
        foreach ($propuestas as &$propuesta) {
            $propuesta['clave_origen'] = FuentesComisionEmpaque::TIENDA_MUNICIPAL.':'.$propuesta['clave_origen'];
            $propuesta['procedencia'] = FuentesComisionEmpaque::TIENDA_MUNICIPAL;
            if ($propuesta['cantidad_origen'] === 0 || $cedisLiquidado) {
                $propuesta['situacion_inicial'] = UnidadComisionEmpaque::SOLAPE_REVISION;
            }
        }

        return $propuestas;
    }

    /** @param  array{hay_cajas: bool, folio: ?string}|null  $op */
    private function propuestasCedis(PedidoBma $pedido, int $areaId, ?array $op): array
    {
        $base = $this->basePedido($pedido, $areaId, FuentesComisionEmpaque::CEDIS_CAJA);
        $base['operacion_folio'] = $op['folio'] ?? null;
        $activas = $pedido->cajas->filter(fn (PedidoBmaCaja $c) => $c->estaActiva());

        if ($activas->isNotEmpty()) {
            return $activas->map(function (PedidoBmaCaja $caja) use ($base, $pedido) {
                return array_merge($base, [
                    'clave_origen' => FuentesComisionEmpaque::CEDIS_CAJA.':'.$caja->id,
                    'procedencia' => FuentesComisionEmpaque::CEDIS_CAJA,
                    'origen_tipo' => 'pedido_bma_cajas',
                    'origen_id' => $caja->id,
                    'cantidad_origen' => max(1, (int) $caja->cantidad),
                    'situacion_inicial' => null,
                    'logistica' => array_replace($base['logistica'], HuellaFuenteComisionEmpaque::datosComerciales($pedido, $caja), [
                        'estado_envio' => $base['logistica']['estado_envio'] ?? ($caja->estaRecolectada() ? 'Recolectado' : 'Pendiente de despacho'),
                    ]),
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
                    'desglose' => $bulto->tipo_bulto,
                    'empacado_at' => $bulto->empacado_at ?: $base['empacado_at'],
                    'empacador_user_id' => $bulto->empacado_por_id ?: $base['empacador_user_id'],
                    'empacador_nombre' => $bulto->empacado_por_id ? $bulto->empacadoPor?->name : $base['empacador_nombre'],
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
            'situacion_inicial' => ($op['consolidada'] ?? false) ? UnidadComisionEmpaque::SOLAPE_REVISION : null,
            'linea' => null,
        ])];
    }

    private function propuestaTienda(PedidoBmaCumplimientoFisico $cumplimiento, int $areaId): array
    {
        $pedido = $cumplimiento->tarea->pedido;
        $solape = $pedido->empacado_at && ! $pedido->es_resguardo
            && ! HuellaFuenteComisionEmpaque::empaqueMunicipalEnTienda($pedido)
            && ($pedido->cajas()->activas()->exists() || $pedido->bultosEmpaque()->exists() || $pedido->numero_cajas > 0);
        return array_merge($this->basePedido($pedido, $areaId, FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO), [
            'clave_origen' => FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO.':'.$cumplimiento->id,
            'procedencia' => FuentesComisionEmpaque::TIENDA_CUMPLIMIENTO,
            'origen_tipo' => 'pedido_bma_cumplimiento_fisico',
            'origen_id' => $cumplimiento->id,
            'empacado_at' => $cumplimiento->empacado_at,
            'empacador_user_id' => $cumplimiento->empacado_por_id ?: $pedido->empacado_por_id,
            'empacador_nombre' => $cumplimiento->empacado_por_id ? $cumplimiento->empacadoPor?->name : $pedido->empacadoPor?->name,
            'cantidad_origen' => (int) $cumplimiento->bultos_salida,
            'situacion_inicial' => $solape ? UnidadComisionEmpaque::SOLAPE_REVISION : null,
            'linea' => null,
            'desglose' => HuellaFuenteComisionEmpaque::desgloseAgrupado($cumplimiento),
            'logistica' => array_replace($this->logistica($pedido), HuellaFuenteComisionEmpaque::datosComerciales($pedido, cumplimiento: $cumplimiento), [
                'paqueteria' => $cumplimiento->tarea?->paqueteria?->nombre ?: $pedido->paqueteria?->nombre,
                'estado_envio' => $cumplimiento->despachada_at ? 'Enviado' : 'Pendiente de despacho',
                'modalidad_preparacion' => $cumplimiento->tarea?->modalidad?->nombre,
                'bultos_salida' => (int) $cumplimiento->bultos_salida,
            ]),
        ]);
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
            'folio_remision' => $pedido->numero_remision ?: $pedido->folio_remision,
            'logistica' => $this->logistica($pedido),
            'es_complemento' => $pedido->esComplemento(),
            'operacion_folio' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function logistica(PedidoBma $pedido): array
    {
        $estadoEnvio = match ($pedido->estatus?->fase_ciclo) {
            CatalogoEstatusPedido::FASE_ENVIADO => 'Enviado',
            CatalogoEstatusPedido::FASE_ENTREGADO => 'Entregado',
            default => null,
        };

        return HuellaFuenteComisionEmpaque::datosComerciales($pedido) + [
            'estado_envio' => $estadoEnvio,
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
    private function guardar(array $propuesta, ?User $responsable, PedidoBma $pedido, array $claves): array
    {
        $huella = HuellaFuenteComisionEmpaque::de(
            $propuesta['cantidad_origen'],
            $propuesta['empacado_at'],
            $pedido->cancelado_at,
            $propuesta['linea']['catalogo_tipo_caja_id'] ?? null,
            $propuesta['desglose'] ?? null,
        );

        $desglose = $propuesta['desglose'] ?? null;
        $propuesta['logistica']['desglose_fuente'] = $desglose;
        $propuesta['lineas_fuente'] = $this->lineasFuente($desglose, $propuesta['linea']);

        return $this->guardarConBloqueo($propuesta, $responsable, $pedido, $huella, $claves);
    }

    private function lineasFuente(string|array|null $desglose, ?array $linea): array
    {
        if ($desglose === null) {
            return $linea ? [$linea] : [];
        }

        $cantidades = is_array($desglose) ? $desglose : [
            'cajas' => (int) ($desglose === 'caja'), 'bolsas' => (int) ($desglose === 'bolsa'),
        ];

        return collect($cantidades)->filter(fn ($cantidad) => $cantidad > 0)
            ->map(fn ($cantidad, $tipo) => ['catalogo_tipo_caja_id' => null, 'cantidad' => $cantidad, 'es_bolsa' => $tipo === 'bolsas'])
            ->values()->all();
    }

    private function guardarConBloqueo(array $propuesta, ?User $responsable, PedidoBma $pedido, string $huella, array $claves): array
    {
        $unidad = UnidadComisionEmpaque::query()->where('clave_origen', $propuesta['clave_origen'])->lockForUpdate()->first();
        $reempaque = PedidoBmaHistorialEstado::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('accion', AccionesHistorialPedidoBma::REVERTIR_EMPAQUE)
            ->exists();

        $mismaFuenteEnCorte = UnidadComisionEmpaque::fuenteYaEnCorte($propuesta['origen_tipo'], $propuesta['origen_id'], $unidad?->id);
        $propuesta['logistica']['fuente_en_otro_corte'] = $mismaFuenteEnCorte;
        $otraRepresentacion = UnidadComisionEmpaque::query()->where('pedido_bma_id', $pedido->id)
            ->where('origen_registro', 'sincronizado')->whereNotIn('clave_origen', $claves)
            ->where(fn ($q) => $q->whereNotNull('corte_id')->orWhere('ancla_decision', 'aceptar')
                ->orWhere(fn ($abierta) => $abierta->where('area_id', '!=', $propuesta['area_id'])
                    ->whereNotIn('situacion', [UnidadComisionEmpaque::REVERTIDA_REVISION, UnidadComisionEmpaque::EXCLUIDA, UnidadComisionEmpaque::RECHAZADA])))
            ->when($pedido->es_resguardo, fn ($q) => $q->where('area_id', $propuesta['area_id']))
            ->exists();
        if ($otraRepresentacion || $mismaFuenteEnCorte) {
            $propuesta['situacion_inicial'] = UnidadComisionEmpaque::SOLAPE_REVISION;
        }

        if (! $unidad) {
            $folios = collect($propuesta['logistica']['remisiones'])->pluck('folio')->filter()->unique();
            $cubiertas = $folios->filter(fn ($folio) => $this->gestionar->cubiertaPorConstancia((int) $propuesta['area_id'], $folio, $propuesta['empacado_at']));
            if ($folios->isNotEmpty() && $folios->count() === $cubiertas->count()) {
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
                'situacion' => ($cubiertas->isNotEmpty() ? UnidadComisionEmpaque::SOLAPE_REVISION : $propuesta['situacion_inicial'])
                    ?: ($reempaque ? UnidadComisionEmpaque::REEMPAQUE_REVISION : UnidadComisionEmpaque::PENDIENTE_CLASIFICACION),
                'revision_estado' => 'no_aplica',
                'huella' => $huella,
                'operacion_folio' => $propuesta['operacion_folio'],
                'es_complemento' => $propuesta['es_complemento'],
            ]);
            $unidad->lineas()->createMany($propuesta['lineas_fuente']);
            if ($cubiertas->isNotEmpty()) {
                $this->valorar->abrirIncidencia($unidad, 'constancia_parcial', $responsable, 'Una remisión de la caja ya está registrada como pagada fuera. Revise qué trabajo corresponde al fondo.', ['folios' => $cubiertas->values()->all()]);
            }
            $this->valorar->marcarTardiaSiCorresponde($unidad);
            $this->valorar->aplicar($unidad->fresh('lineas'));
            $this->aplicarCancelacion($unidad->fresh(), $pedido, $responsable);
            $this->gestionar->marcarSiDuplicado($unidad->fresh());

            return ['nueva' => true, 'actualizada' => false, 'diferencia' => (bool) $unidad->incorporacion_tardia, 'mensaje' => $unidad->incorporacion_tardia ? 'Incorporación tardía '.$unidad->clave_origen : null];
        }

        if ($unidad->corte_id) {
            $diferencia = (int) $unidad->pedido_bma_id !== (int) $pedido->id || $unidad->huella !== $huella
                || ($unidad->logistica['huella_comercial'] ?? $propuesta['logistica']['huella_comercial']) !== $propuesta['logistica']['huella_comercial'];
            $mensaje = $diferencia ? 'La fuente cambió después del corte. Revise si corresponde un ajuste.' : null;
            if ($mensaje) {
                $this->valorar->abrirIncidencia($unidad, 'diferencia_fuente', $responsable, $mensaje, [
                    'huella' => $huella.':'.$propuesta['logistica']['huella_comercial'],
                ]);
            }
            if ((int) $unidad->pedido_bma_id === (int) $pedido->id) {
                $this->aplicarCancelacion($unidad, $pedido, $responsable);
            }

            return ['nueva' => false, 'actualizada' => false, 'diferencia' => $diferencia, 'mensaje' => $mensaje];
        }

        return $this->actualizarAbierta($unidad, $propuesta, $huella, $pedido, $responsable);
    }

    /** @param  array<string, mixed>  $propuesta
     * @return array{nueva: bool, actualizada: bool, diferencia: bool, mensaje: ?string}
     */
    private function actualizarAbierta(UnidadComisionEmpaque $unidad, array $propuesta, string $huella, PedidoBma $pedido, ?User $responsable): array
    {
        $antes = $unidad->only(['situacion', 'logistica', 'huella', 'importe_centavos', 'empacador_user_id', 'empacador_nombre', 'cliente_nombre']);
        $huellaPrevia = (string) $unidad->huella;
        $anclaPrevia = $unidad->ancla_decision;
        $fuentePrevia = $unidad->logistica['huella_comercial'] ?? null;
        $cambioComercial = $fuentePrevia !== null && $fuentePrevia !== $propuesta['logistica']['huella_comercial'];
        $cambioPedido = (int) $unidad->pedido_bma_id !== (int) $pedido->id;
        $material = $cambioPedido || ($huellaPrevia !== '' && $huellaPrevia !== $huella);
        $desgloseCambio = ($unidad->logistica['desglose_fuente'] ?? null) != ($propuesta['desglose'] ?? null);
        $cantidadCambio = (int) $unidad->cantidad_origen !== (int) $propuesta['cantidad_origen'];
        $unidad->fill([
            'pedido_bma_id' => $pedido->id,
            'empacado_at' => $propuesta['empacado_at'],
            'empacador_user_id' => $propuesta['empacador_user_id'],
            'empacador_nombre' => $propuesta['empacador_nombre'],
            'cliente_nombre' => $propuesta['cliente_nombre'],
            'folio_pedido' => $propuesta['folio_pedido'],
            'folio_remision' => $propuesta['folio_remision'],
            'logistica' => array_replace($unidad->logistica ?? [], $propuesta['logistica']),
            'cantidad_origen' => $propuesta['cantidad_origen'],
            'operacion_folio' => $propuesta['operacion_folio'],
            'huella' => $huella,
            'es_complemento' => $propuesta['es_complemento'],
        ]);

        $cubierta = ! $material && $this->valorar->anclaCubre($unidad, ['excluir', 'descartar']);
        if ($cubierta) {
            $unidad->situacion = UnidadComisionEmpaque::EXCLUIDA;
        } elseif ($propuesta['situacion_inicial'] === UnidadComisionEmpaque::SOLAPE_REVISION
            && in_array($unidad->situacion, [UnidadComisionEmpaque::ELEGIBLE, UnidadComisionEmpaque::PENDIENTE_CLASIFICACION, UnidadComisionEmpaque::PENDIENTE_CONCILIACION], true)
            && (($propuesta['logistica']['fuente_en_otro_corte'] ?? false) || ! $this->valorar->anclaCubre($unidad, ['aceptar']))) {
            $unidad->situacion = UnidadComisionEmpaque::SOLAPE_REVISION;
        }

        if (! $cubierta && $unidad->situacion === UnidadComisionEmpaque::REVERTIDA_REVISION) {
            $unidad->situacion = UnidadComisionEmpaque::REEMPAQUE_REVISION;
            $unidad->diferencia = 'El empaque volvió después de una reversión. Revise si es corrección o trabajo distinto.';
        }

        if ($cantidadCambio || $desgloseCambio || $cambioPedido) {
            $previas = $unidad->lineas()->orderBy('id')->get(['catalogo_tipo_caja_id', 'cantidad', 'es_bolsa'])->toArray();
            $unidad->lineas()->delete();
            $unidad->lineas()->createMany($propuesta['lineas_fuente']);
            if ($previas !== []) {
                $unidad->logistica = array_replace($unidad->logistica, ['revisar_desglose' => true]);
                $unidad->diferencia = $anclaPrevia
                    ? 'Cambió el desglose de origen. Se conserva la decisión «'.$anclaPrevia.'» como antecedente.'
                    : 'Cambió el desglose de origen. Compruebe la clasificación antes de liquidar.';
                $this->valorar->abrirIncidencia($unidad, 'cambio_desglose', $responsable, $unidad->diferencia, [
                    'huella' => $huella,
                    'lineas_previas' => $previas,
                ]);
            }
        }

        if ($material && ! empty($propuesta['linea']) && ! $cantidadCambio && $unidad->lineas()->count() === 1) {
            $unidad->lineas()->where('es_bolsa', false)->update(['catalogo_tipo_caja_id' => $propuesta['linea']['catalogo_tipo_caja_id']]);
        }
        if (($material || $cambioComercial) && $unidad->conciliacion) {
            $this->valorar->abrirIncidencia($unidad, 'cambio_comercial', $responsable, 'Cambió la fuente del empaque. Revise la conciliación anterior.', [
                'huella' => $huella.':'.$propuesta['logistica']['huella_comercial'],
                'conciliacion_previa' => $unidad->conciliacion,
            ]);
        }
        $cambioFecha = $unidad->isDirty('empacado_at');
        $unidad->save();
        if ($cambioFecha && ! $unidad->incorporacion_tardia) {
            $this->valorar->marcarTardiaSiCorresponde($unidad);
        }
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
            'actualizada' => $antes != $unidad->fresh()->only(array_keys($antes)),
            'diferencia' => $material || $cambioComercial || $cantidadCambio,
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

    private function marcarAusente(UnidadComisionEmpaque $unidad, ?User $responsable, array &$conteo): void
    {
        if ($this->conservarDecisionAnteAusencia($unidad)) {
            return;
        }
        $mensaje = 'La fuente dejó de estar activa o quedó fuera del rango autorizado.';
        if (! $unidad->corte_id && $unidad->situacion !== UnidadComisionEmpaque::REVERTIDA_REVISION) {
            $unidad->update([
                'situacion' => UnidadComisionEmpaque::REVERTIDA_REVISION,
                'importe_centavos' => null,
                'cantidad_comisionable' => null,
                'diferencia' => $mensaje,
            ]);
            $conteo['diferencias']++;
        }
        $this->valorar->abrirIncidencia($unidad, 'reversion', $responsable, $mensaje, ['huella' => $unidad->huella]);
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

    private function marcarExcepcionResguardo(array $areasAutorizadas, int $pedidoId): void
    {
        $pedidoIds = UnidadComisionEmpaque::query()
            ->where('pedido_bma_id', $pedidoId)
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
                UnidadComisionEmpaque::query()->where('pedido_bma_id', $pedidoId)->whereNull('corte_id')->where('excepcion_resguardo', false)
                    ->when($areasAutorizadas !== null, fn ($q) => $q->whereIn('area_id', $areasAutorizadas))->update(['excepcion_resguardo' => true]);
            }
        }
    }
}
