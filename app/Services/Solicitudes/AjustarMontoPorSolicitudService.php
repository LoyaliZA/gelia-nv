<?php

namespace App\Services\Solicitudes;

use App\Models\CatalogoEstadoSolicitud;
use App\Models\CatalogoListaDescuento;
use App\Models\Cliente;
use App\Models\ClienteModalidadEvento;
use App\Models\Escalonamiento\EscalonamientoConciliacionSolicitud;
use App\Models\SolicitudBeneficio;
use App\Models\SolicitudTag;
use App\Services\Clientes\ClasificacionComercialCliente;
use App\Services\Escalonamiento\ConciliacionSolicitudEscalonamiento;
use App\Services\Escalonamiento\EscalonamientoAutoridad;
use App\Services\Escalonamiento\ProyeccionClienteEscalonamiento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AjustarMontoPorSolicitudService
{
    public function __construct(
        private EscalonamientoService $escalonamiento,
        private EscalonamientoAutoridad $autoridad,
        private ProyeccionClienteEscalonamiento $proyeccionCliente,
        private ConciliacionSolicitudEscalonamiento $conciliacion,
        private ImporteProvisionalSolicitudService $importes,
    ) {}

    /**
     * Bloquea las solicitudes del cliente por id ascendente. Debe llamarse dentro de
     * una transacción y antes de bloquear al cliente o las filas de beneficio.
     * Ese orden coincide con el pago y con la conciliación de Fase 2A, que toman
     * primero la solicitud.
     */
    public function prepararBloqueo(int $clienteId): void
    {
        if ($clienteId <= 0) {
            return;
        }

        $ids = SolicitudTag::query()
            ->where('cliente_id', $clienteId)
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            SolicitudTag::query()->whereKey($id)->lockForUpdate()->first();
        }
    }

    /**
     * Copia lista, tipo o TAG solo cuando la regla vigente lo permite, y deja
     * constancia de la concesión. No suma al historial de venta.
     *
     * @return array{antes: array<string, mixed>, despues: array<string, mixed>}
     */
    public function aplicarBeneficios(SolicitudTag $solicitud): array
    {
        return DB::transaction(function () use ($solicitud) {
            if (! $solicitud->cliente_id) {
                return [];
            }

            $this->prepararBloqueo((int) $solicitud->cliente_id);
            $cliente = Cliente::query()->lockForUpdate()->find($solicitud->cliente_id);
            if (! $cliente) {
                return [];
            }

            $solicitud->setRelation('cliente', $cliente);
            $solicitud->loadMissing('proceso');
            $antes = $this->capturarSnapshotCliente($cliente);
            $listaAnterior = $this->valor($cliente->lista_actual_id);
            $tipoAnterior = $this->valor($cliente->catalogo_tipo_cliente_id);
            $vendedorAnterior = $this->valor($cliente->vendedor_id);
            $clasificacion = app(ClasificacionComercialCliente::class);
            $esMenudeo = $clasificacion->esMenudeo($cliente);
            $pideConversion = (bool) $solicitud->convertir_menudeo_mayoreo;
            $autorizada = $this->solicitudAutorizada($solicitud);
            $bloquearListaMenudeo = $esMenudeo && (! $pideConversion || ! $autorizada);
            $hayBeneficio = $solicitud->catalogo_lista_descuento_id
                || $solicitud->catalogo_tipo_cliente_id
                || ($solicitud->vendedor_id && $this->solicitudAsignaTag($solicitud))
                || $pideConversion;
            if ($autorizada && $cliente->es_inactivo && $hayBeneficio) {
                $this->aplicarRestauracionesPendientes($cliente);
                $cliente->es_inactivo = false;
            }

            if (
                $solicitud->catalogo_lista_descuento_id
                && ! $cliente->es_inactivo
                && ! $cliente->lista_bloqueada
                && ! $this->autoridad->clienteGobernadoPorModulo($cliente)
                && ! $bloquearListaMenudeo
            ) {
                $cliente->lista_actual_id = $solicitud->catalogo_lista_descuento_id;
                $this->conceder($cliente, $solicitud, SolicitudBeneficio::CAMPO_LISTA, $listaAnterior, $this->valor($cliente->lista_actual_id));
            }

            if (
                $pideConversion
                && $autorizada
                && ! $cliente->es_inactivo
                && ! $cliente->lista_bloqueada
                && ! $this->autoridad->clienteGobernadoPorModulo($cliente)
            ) {
                if ($esMenudeo) {
                    $this->aplicarConversionMenudeo($cliente, $solicitud, $clasificacion, $listaAnterior);
                } elseif ($clasificacion->conversionVigente($cliente)) {
                    $this->ratificarConversion($cliente, $solicitud, $clasificacion);
                }
            }

            if ($solicitud->catalogo_tipo_cliente_id && ! $cliente->es_inactivo) {
                $cliente->catalogo_tipo_cliente_id = $solicitud->catalogo_tipo_cliente_id;
                $this->conceder($cliente, $solicitud, SolicitudBeneficio::CAMPO_TIPO, $tipoAnterior, $this->valor($cliente->catalogo_tipo_cliente_id));
            }

            if ($solicitud->vendedor_id && $this->solicitudAsignaTag($solicitud) && ! $cliente->es_inactivo) {
                $cliente->vendedor_id = $solicitud->vendedor_id;
                $this->conceder($cliente, $solicitud, SolicitudBeneficio::CAMPO_VENDEDOR, $vendedorAnterior, $this->valor($cliente->vendedor_id));
            }

            $cliente->save();
            $solicitud->loadMissing('proceso');
            if ($solicitud->exists && $solicitud->proceso && ! $solicitud->proceso->esOperativo()) {
                $this->importes->registrarCotizacion($solicitud, Auth::id());
            }
            $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);

            return [
                'antes' => $antes,
                'despues' => $this->capturarSnapshotCliente($cliente),
            ];
        });
    }

    /**
     * Revierte solo el beneficio que esta solicitud todavía sostiene. La lista
     * resultante es la que califica el acumulado oficial. No modifica
     * monto_venta_actual ni la evidencia documental.
     *
     * @return array{antes: array<string, mixed>, despues: array<string, mixed>}
     */
    public function revertirBeneficios(SolicitudTag $solicitud, ?int $usuarioId = null, ?string $origen = null): array
    {
        return DB::transaction(function () use ($solicitud, $usuarioId) {
            if (! $solicitud->cliente_id) {
                return [];
            }

            $this->prepararBloqueo((int) $solicitud->cliente_id);
            $this->conciliacion->liberar($solicitud);
            $cliente = Cliente::query()->lockForUpdate()->find($solicitud->cliente_id);
            if (! $cliente) {
                return [];
            }

            SolicitudBeneficio::query()
                ->where('cliente_id', $cliente->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $montoOficial = (string) $cliente->monto_venta_actual;
            $menudeoAntes = (bool) $cliente->es_menudeo;
            $antes = $this->capturarSnapshotCliente($cliente);
            $consolidada = $this->importes->tieneCierrePorCorte($solicitud);
            $this->importes->revocarSiVigente($solicitud, $usuarioId, $origen ?? 'reversion');
            $this->revertirCampo($cliente, $solicitud, SolicitudBeneficio::CAMPO_MODALIDAD, $consolidada);
            $this->revertirCampo($cliente, $solicitud, SolicitudBeneficio::CAMPO_LISTA, $consolidada);
            $this->revertirCampo($cliente, $solicitud, SolicitudBeneficio::CAMPO_TIPO, $consolidada);
            $this->revertirCampo($cliente, $solicitud, SolicitudBeneficio::CAMPO_VENDEDOR, $consolidada);
            if ((bool) $cliente->es_menudeo !== $menudeoAntes) {
                app(ClasificacionComercialCliente::class)->registrarEvento($cliente, $usuarioId, (int) $solicitud->id);
            }

            if ((string) $cliente->monto_venta_actual !== $montoOficial) {
                $cliente->monto_venta_actual = $montoOficial;
            }

            $cliente->save();
            $solicitud->monto_aplicado_al_cliente = 0;
            $solicitud->save();
            $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);
            $resultado = [
                'antes' => $antes,
                'despues' => $this->capturarSnapshotCliente($cliente),
            ];
            $incidencia = $this->incidenciaListaConservada($cliente, $consolidada);
            if ($incidencia !== null) {
                $resultado['incidencia_lista'] = $incidencia;
            }

            return $resultado;
        });
    }

    /**
     * El proyectado de decisión es el acumulado oficial. El resto se informa por separado
     * y no se suma: un pago o una cotización no son una venta publicada, y un documento
     * ya incluido no puede contarse otra vez.
     *
     * @return array<string, mixed>
     */
    public function proyectarMonto(Cliente $cliente, SolicitudTag $solicitud, float $montoFinal): array
    {
        $desglose = $this->desglose($cliente, $solicitud, $montoFinal);
        $oficial = (float) $desglose['venta_oficial'];

        return $desglose + [
            'base' => $oficial,
            'proyectado' => $oficial,
            'aplicara_monto' => false,
            'base_lista' => $this->importes->baseDecisionLista($cliente, $solicitud, $this->dinero($montoFinal)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function desglose(Cliente $cliente, ?SolicitudTag $enCurso = null, ?float $montoEnCurso = null): array
    {
        $oficial = $this->dinero($this->proyeccionCliente->montoAcumulado($cliente));
        $solicitudes = SolicitudTag::query()
            ->with('estado')
            ->where('cliente_id', $cliente->id)
            ->get();
        $asignadas = EscalonamientoConciliacionSolicitud::query()
            ->whereIn('solicitud_tag_id', $solicitudes->pluck('id'))
            ->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA)
            ->get()
            ->groupBy('solicitud_tag_id');

        $cubetas = [
            'cotizacion_pendiente' => '0.00',
            'pago_confirmado_sin_conciliar' => '0.00',
            'venta_respaldada_en_acumulado' => '0.00',
            'venta_documentada_fuera' => '0.00',
            'venta_documentada_inclusion_indeterminada' => '0.00',
            'historica_sin_respaldo' => '0.00',
            'excluidas' => '0.00',
        ];
        $efecto = 'sin_cambio';
        $motivo = 'No hay un monto adicional que modificar en la lista.';

        foreach ($solicitudes as $item) {
            $esCurso = $enCurso && (int) $item->id === (int) $enCurso->id;
            $reclamo = $esCurso && $montoEnCurso !== null
                ? $this->dinero($montoEnCurso)
                : $this->reclamoAlmacenado($item);
            $filas = $asignadas->get($item->id, collect());
            $asignado = '0.00';
            $incluida = '0.00';
            $fuera = '0.00';
            $indeterminada = '0.00';
            foreach ($filas as $fila) {
                $importe = $this->dinero($fila->importe_asignado);
                $asignado = bcadd($asignado, $importe, 2);
                if ($fila->inclusion_acumulado === EscalonamientoConciliacionSolicitud::INCLUSION_INCLUIDA) {
                    $incluida = bcadd($incluida, $importe, 2);
                } elseif ($fila->inclusion_acumulado === EscalonamientoConciliacionSolicitud::INCLUSION_FUERA) {
                    $fuera = bcadd($fuera, $importe, 2);
                } else {
                    $indeterminada = bcadd($indeterminada, $importe, 2);
                }
            }
            if ($this->excluida($item)) {
                $cubetas['excluidas'] = bcadd($cubetas['excluidas'], $reclamo, 2);
                if ($esCurso) {
                    $efecto = 'excluida';
                    $motivo = 'La solicitud está cancelada, rechazada o vencida. Su monto no entra en la proyección.';
                }
                continue;
            }

            $cubetas['venta_respaldada_en_acumulado'] = bcadd($cubetas['venta_respaldada_en_acumulado'], $incluida, 2);
            $cubetas['venta_documentada_fuera'] = bcadd($cubetas['venta_documentada_fuera'], $fuera, 2);
            $cubetas['venta_documentada_inclusion_indeterminada'] = bcadd($cubetas['venta_documentada_inclusion_indeterminada'], $indeterminada, 2);

            $resto = bcsub($reclamo, $asignado, 2);
            if (bccomp($resto, '0.00', 2) < 0) {
                $resto = '0.00';
            }
            $pago = $esCurso || $item->pago_confirmado;
            if ($filas->isEmpty() && $item->cubierto_por_carga_masiva) {
                $cubetas['historica_sin_respaldo'] = bcadd($cubetas['historica_sin_respaldo'], $reclamo, 2);
                if ($esCurso) {
                    $efecto = 'retenida';
                    $motivo = 'La marca histórica no tiene documento. No se suma ni se resta del acumulado oficial.';
                }
                continue;
            }
            if ($pago && bccomp($resto, '0.00', 2) > 0) {
                $cubetas['pago_confirmado_sin_conciliar'] = bcadd($cubetas['pago_confirmado_sin_conciliar'], $resto, 2);
            } elseif (! $pago && bccomp($resto, '0.00', 2) > 0) {
                $cubetas['cotizacion_pendiente'] = bcadd($cubetas['cotizacion_pendiente'], $resto, 2);
            }

            if (! $esCurso) {
                continue;
            }
            $determinable = bccomp($resto, '0.00', 2) === 0
                && bccomp($fuera, '0.00', 2) === 0
                && bccomp($indeterminada, '0.00', 2) === 0
                && bccomp($incluida, '0.00', 2) > 0;
            if (! $determinable) {
                $efecto = 'retenida';
                $motivo = 'La cobertura no está determinada. La lista no cambia al confirmar el pago.';
                continue;
            }
            $item->loadMissing('listaDescuento');
            $requerido = $item->listaDescuento?->monto_requerido;
            $base = $this->importes->baseConDocumentoIncluido($cliente, $item, $oficial);
            if ($requerido !== null && bccomp($base, $this->dinero($requerido), 2) < 0) {
                $efecto = 'pago_insuficiente';
                $motivo = 'El acumulado no alcanza la lista solicitada. El documento conciliado ya está en el oficial y no se vuelve a sumar.';
                continue;
            }
            $efecto = 'sin_cambio';
            $motivo = 'El documento ya está en el acumulado oficial. Confirmar el pago no modifica la lista.';
        }

        if ($enCurso && ! $solicitudes->contains(fn (SolicitudTag $item) => (int) $item->id === (int) $enCurso->id)) {
            $efecto = 'retenida';
            $motivo = 'La solicitud no pertenece al cliente. La lista no cambia.';
        }

        return [
            'venta_oficial' => $oficial,
            'cotizacion_pendiente' => $cubetas['cotizacion_pendiente'],
            'pago_confirmado_sin_conciliar' => $cubetas['pago_confirmado_sin_conciliar'],
            'venta_respaldada_en_acumulado' => $cubetas['venta_respaldada_en_acumulado'],
            'venta_documentada_fuera' => $cubetas['venta_documentada_fuera'],
            'venta_documentada_inclusion_indeterminada' => $cubetas['venta_documentada_inclusion_indeterminada'],
            'historica_sin_respaldo' => $cubetas['historica_sin_respaldo'],
            'excluidas' => $cubetas['excluidas'],
            'efecto_lista' => $efecto,
            'motivo_lista' => $motivo,
            'decision_habilitada' => in_array($efecto, ['sin_cambio', 'pago_insuficiente'], true),
        ];
    }

    /**
     * Registra el pago como auditoría y puede asignar la lista. No modifica monto_venta_actual.
     *
     * @return array{antes: array<string, mixed>, despues: array<string, mixed>}
     */
    public function aplicarPagoConfirmado(
        SolicitudTag $solicitud,
        float $montoFinal,
        ?int $usuarioId = null,
        bool $asignarListaSolicitada = false,
    ): array {
        $cliente = $this->clienteDe($solicitud);
        if (! $cliente) {
            return [];
        }

        return DB::transaction(function () use ($solicitud, $montoFinal, $usuarioId, $asignarListaSolicitada) {
            $solicitud = SolicitudTag::query()->lockForUpdate()->findOrFail($solicitud->id);
            $cliente = $this->clienteDe($solicitud);
            if (! $cliente) {
                return [];
            }
            $cliente = Cliente::query()->lockForUpdate()->findOrFail($cliente->id);
            $cliente->load(['listaDescuento', 'vendedor', 'tipo']);
            $antes = $this->capturarSnapshotCliente($cliente);
            if ($solicitud->pago_confirmado) {
                return [
                    'antes' => $antes,
                    'despues' => $antes,
                    'repetido' => true,
                ];
            }

            $this->importes->sustituirPorPago($solicitud, $this->dinero($montoFinal), $usuarioId);
            $solicitud->monto_aplicado_al_cliente = $this->dinero($montoFinal);
            $solicitud->pago_confirmado = true;

            if (
                $asignarListaSolicitada
                && $solicitud->catalogo_lista_descuento_id
                && ! $cliente->es_inactivo
                && ! $cliente->lista_bloqueada
                && ! $this->autoridad->clienteGobernadoPorModulo($cliente)
            ) {
                $cliente->lista_actual_id = $solicitud->catalogo_lista_descuento_id;
            }

            $cliente->save();
            $solicitud->save();
            $cliente->refresh()->load(['listaDescuento', 'vendedor', 'tipo']);

            return [
                'antes' => $antes,
                'despues' => $this->capturarSnapshotCliente($cliente),
                'repetido' => false,
            ];
        });
    }

    /**
     * Tras pisar el monto con Wizerp: no volver a sumar esa solicitud,
     * y no restar una capa que el CSV ya reemplazó.
     */
    public function marcarCubiertasPorCarga(Cliente $cliente, float $montoGeliaAntes, float $montoWizerp): int
    {
        // CSV de acumulados no acredita una remisión. Conservar marcas históricas intactas.
        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function capturarSnapshotCliente(Cliente $cliente): array
    {
        $cliente->loadMissing(['listaDescuento', 'vendedor', 'tipo']);

        return [
            'monto_venta' => $cliente->monto_venta_actual,
            'lista_id' => $cliente->lista_actual_id,
            'lista_nombre' => $cliente->listaDescuento?->nombre,
            'tag_vendedor_id' => $cliente->vendedor_id,
            'tag_vendedor_nombre' => $cliente->vendedor?->name,
            'tipo_cliente_id' => $cliente->catalogo_tipo_cliente_id,
            'tipo_cliente_nombre' => $cliente->tipo?->nombre,
        ];
    }

    public function estaCubierta(SolicitudTag $solicitud): bool
    {
        return (bool) $solicitud->cubierto_por_carga_masiva;
    }

    public function capaPendiente(SolicitudTag $solicitud): float
    {
        // No sumar este valor a monto_venta_actual: es la parte sin conciliar, no una venta oficial.
        if ($this->excluida($solicitud) || $solicitud->cubierto_por_carga_masiva) {
            return 0.0;
        }
        $asignado = EscalonamientoConciliacionSolicitud::query()
            ->where('solicitud_tag_id', $solicitud->id)
            ->where('estado', EscalonamientoConciliacionSolicitud::ESTADO_ASIGNADA)
            ->sum('importe_asignado');

        return max(0, round((float) $solicitud->monto_aplicado_al_cliente - (float) $asignado, 2));
    }

    private function reclamoAlmacenado(SolicitudTag $solicitud): string
    {
        if ($solicitud->pago_confirmado) {
            return $this->dinero($solicitud->monto_aplicado_al_cliente);
        }

        return $this->dinero($solicitud->monto_cotizado);
    }

    private function excluida(SolicitudTag $solicitud): bool
    {
        $nombre = $solicitud->estado?->nombre;

        return in_array($nombre, ['Cancelada', 'Incorrecta', 'Rechazada'], true);
    }

    private function incidenciaListaConservada(Cliente $cliente, bool $consolidada): ?string
    {
        if (! $consolidada) {
            return null;
        }

        $cliente->loadMissing('listaDescuento');
        $requerido = $cliente->listaDescuento?->monto_requerido;
        if ($requerido === null || bccomp($this->dinero($cliente->monto_venta_actual), $this->dinero($requerido), 2) >= 0) {
            return null;
        }

        return 'La lista se conserva por política administrativa. El oficial no alcanza el umbral y el corte no la respalda.';
    }

    private function dinero(float|int|string|null $valor): string
    {
        if ($valor === null || $valor === '') {
            return '0.00';
        }
        if (is_int($valor) || is_float($valor)) {
            $valor = number_format($valor, 2, '.', '');
        }

        return bcadd((string) $valor, '0', 2);
    }

    private function revertirCampo(Cliente $cliente, SolicitudTag $solicitud, string $campo, bool $consolidada = false): void
    {
        $actual = $this->valorActual($cliente, $campo);
        $propio = $this->concesionVigente($solicitud, $campo);

        if ($consolidada && $campo === SolicitudBeneficio::CAMPO_LISTA) {
            if ($propio) {
                $this->cerrarConcesion(
                    $propio,
                    SolicitudBeneficio::ESTADO_REEMPLAZADO,
                    'La lista se conserva por política administrativa. El corte no la respalda documentalmente.',
                );
            }

            return;
        }

        if ($campo === SolicitudBeneficio::CAMPO_MODALIDAD && $propio && $this->modalidadRatificada($cliente, $solicitud, $propio)) {
            $this->cerrarConcesion($propio, SolicitudBeneficio::ESTADO_REEMPLAZADO, 'Una solicitud o una acción posterior ratificó la modalidad.');

            return;
        }

        if ($campo === SolicitudBeneficio::CAMPO_MODALIDAD && $propio && $this->mismoValor($propio->valor_aplicado, $actual) && ($propio->valor_anterior === null || $propio->valor_anterior === '')) {
            $this->cerrarConcesion($propio, SolicitudBeneficio::ESTADO_REEMPLAZADO, 'No hay procedencia verificable para restaurar la modalidad.');

            return;
        }

        if ($this->otraSolicitudSostiene($solicitud, $campo, $actual)) {
            $this->cerrarConcesion($propio, SolicitudBeneficio::ESTADO_REEMPLAZADO, 'Otra solicitud vigente sostiene el beneficio.');

            return;
        }

        if ($propio && $this->mismoValor($propio->valor_aplicado, $actual)) {
            if ($cliente->es_inactivo) {
                $this->cerrarConcesion(
                    $propio,
                    SolicitudBeneficio::ESTADO_RESTAURACION_PENDIENTE,
                    'El cliente está inactivo. La restauración queda pendiente.',
                );

                return;
            }
            $this->restaurarCampo($cliente, $campo, $propio);
            $this->cerrarConcesion($propio, SolicitudBeneficio::ESTADO_REVOCADO, 'La solicitud ya no sostiene el beneficio.');

            return;
        }

        if ($propio) {
            $this->cerrarConcesion($propio, SolicitudBeneficio::ESTADO_REEMPLAZADO, 'El valor vigente ya no corresponde a esta solicitud.');

            return;
        }

        if (
            $campo === SolicitudBeneficio::CAMPO_LISTA
            && ! $cliente->es_inactivo
            && ! $this->hayConcesionVigente($cliente, $campo)
        ) {
            $antes = $cliente->lista_actual_id;
            $this->recalcularListaCliente($cliente);
            if ($this->esListaInferior($cliente->lista_actual_id, $antes)) {
                $cliente->lista_actual_id = $antes;
            }
        }
    }

    private function aplicarRestauracionesPendientes(Cliente $cliente): void
    {
        $pendientes = SolicitudBeneficio::query()
            ->where('cliente_id', $cliente->id)
            ->where('estado', SolicitudBeneficio::ESTADO_RESTAURACION_PENDIENTE)
            ->orderBy('id')
            ->get();
        foreach ($pendientes as $pendiente) {
            $actual = $this->valorActual($cliente, $pendiente->campo);
            $vigentePosterior = SolicitudBeneficio::query()
                ->where('cliente_id', $cliente->id)
                ->where('campo', $pendiente->campo)
                ->where('estado', SolicitudBeneficio::ESTADO_VIGENTE)
                ->where('id', '>', $pendiente->id)
                ->exists();
            if ($vigentePosterior || ! $this->mismoValor($pendiente->valor_aplicado, $actual)) {
                $this->cerrarConcesion($pendiente, SolicitudBeneficio::ESTADO_REEMPLAZADO, 'Una autorización posterior sostiene el valor.');

                continue;
            }
            $this->restaurarCampo($cliente, $pendiente->campo, $pendiente);
            $this->cerrarConcesion($pendiente, SolicitudBeneficio::ESTADO_REVOCADO, 'Se restauró el valor al reactivar.');
        }
    }

    private function restaurarCampo(Cliente $cliente, string $campo, SolicitudBeneficio $concesion): void
    {
        if ($campo === SolicitudBeneficio::CAMPO_MODALIDAD) {
            $cliente->es_menudeo = $concesion->valor_anterior === '1';
            if ($concesion->valor_anterior === '1') {
                $cliente->modalidad_origen = ClasificacionComercialCliente::ORIGEN_REVERSION;
                $cliente->conversion_solicitud_id = null;
                $cliente->conversion_usuario_id = null;
                $cliente->conversion_autorizada_at = null;
            }

            return;
        }

        if ($campo === SolicitudBeneficio::CAMPO_LISTA) {
            if ($cliente->es_menudeo) {
                if ($concesion->valor_anterior !== null && $concesion->valor_anterior !== '') {
                    $cliente->lista_actual_id = (int) $concesion->valor_anterior;
                }

                return;
            }
            $this->recalcularListaCliente($cliente);

            return;
        }

        $anterior = $concesion->valor_anterior === null ? null : (int) $concesion->valor_anterior;
        if ($campo === SolicitudBeneficio::CAMPO_TIPO) {
            $cliente->catalogo_tipo_cliente_id = $anterior;
        }
        if ($campo === SolicitudBeneficio::CAMPO_VENDEDOR) {
            $cliente->vendedor_id = $anterior;
        }
    }

    private function conceder(Cliente $cliente, SolicitudTag $solicitud, string $campo, ?string $anterior, ?string $aplicado): void
    {
        if (! $solicitud->id) {
            return;
        }

        $vigente = $this->concesionVigente($solicitud, $campo);
        if ($vigente && $this->mismoValor($vigente->valor_aplicado, $aplicado)) {
            return;
        }

        if ($vigente) {
            $this->cerrarConcesion($vigente, SolicitudBeneficio::ESTADO_REEMPLAZADO, 'Una concesión posterior de la misma solicitud reemplazó el valor.');
        }

        SolicitudBeneficio::query()->create([
            'cliente_id' => $cliente->id,
            'solicitud_tag_id' => $solicitud->id,
            'campo' => $campo,
            'valor_anterior' => $anterior,
            'valor_aplicado' => $aplicado,
            'estado' => SolicitudBeneficio::ESTADO_VIGENTE,
            'motivo' => 'Concesión provisional mientras la solicitud está vigente.',
        ]);
    }

    private function concesionVigente(SolicitudTag $solicitud, string $campo): ?SolicitudBeneficio
    {
        if (! $solicitud->id) {
            return null;
        }

        return SolicitudBeneficio::query()
            ->where('solicitud_tag_id', $solicitud->id)
            ->where('campo', $campo)
            ->where('estado', SolicitudBeneficio::ESTADO_VIGENTE)
            ->orderByDesc('id')
            ->first();
    }

    private function otraSolicitudSostiene(SolicitudTag $solicitud, string $campo, ?string $actual): bool
    {
        $ids = $this->idsSolicitudesVigentes($solicitud);
        if ($ids === []) {
            return false;
        }

        $consulta = SolicitudBeneficio::query()
            ->whereIn('solicitud_tag_id', $ids)
            ->where('campo', $campo)
            ->where('estado', SolicitudBeneficio::ESTADO_VIGENTE);

        if ($actual === null) {
            $consulta->whereNull('valor_aplicado');
        } else {
            $consulta->where('valor_aplicado', $actual);
        }

        if ($consulta->exists()) {
            return true;
        }

        if ($campo === SolicitudBeneficio::CAMPO_MODALIDAD || $actual === null || ! ctype_digit($actual)) {
            return false;
        }

        $sinConstancia = SolicitudTag::query()
            ->whereIn('id', $ids)
            ->whereNotIn('id', SolicitudBeneficio::query()->where('campo', $campo)->select('solicitud_tag_id'));
        $id = (int) $actual;

        return match ($campo) {
            SolicitudBeneficio::CAMPO_LISTA => (clone $sinConstancia)->where('catalogo_lista_descuento_id', $id)->exists(),
            SolicitudBeneficio::CAMPO_TIPO => (clone $sinConstancia)->where('catalogo_tipo_cliente_id', $id)->exists(),
            SolicitudBeneficio::CAMPO_VENDEDOR => (clone $sinConstancia)
                ->where('vendedor_id', $id)
                ->whereHas('proceso', function ($proceso) {
                    $proceso->where(function ($nombre) {
                        $nombre->where('nombre', 'like', '%ASIGNAR TAG%')
                            ->orWhere('nombre', 'like', '%ASIGNAR CLIENTE%');
                    });
                })
                ->exists(),
            default => false,
        };
    }

    /**
     * @return array<int, int>
     */
    private function idsSolicitudesVigentes(SolicitudTag $solicitud): array
    {
        if (! $solicitud->cliente_id) {
            return [];
        }

        $estados = array_values(array_filter([
            CatalogoEstadoSolicitud::idDe('Respondida'),
            CatalogoEstadoSolicitud::idDe('Verificada'),
        ]));
        if ($estados === []) {
            return [];
        }

        return SolicitudTag::query()
            ->where('cliente_id', $solicitud->cliente_id)
            ->where('id', '!=', $solicitud->id)
            ->whereIn('catalogo_estado_solicitud_id', $estados)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function hayConcesionVigente(Cliente $cliente, string $campo): bool
    {
        return SolicitudBeneficio::query()
            ->where('cliente_id', $cliente->id)
            ->where('campo', $campo)
            ->where('estado', SolicitudBeneficio::ESTADO_VIGENTE)
            ->exists();
    }

    private function esListaInferior(mixed $nueva, mixed $anterior): bool
    {
        if ($nueva === $anterior || $anterior === null) {
            return false;
        }
        if ($nueva === null) {
            return true;
        }

        $requerido = CatalogoListaDescuento::query()
            ->whereIn('id', [$nueva, $anterior])
            ->pluck('monto_requerido', 'id');

        return bccomp((string) ($requerido[$nueva] ?? 0), (string) ($requerido[$anterior] ?? 0), 2) < 0;
    }

    private function cerrarConcesion(?SolicitudBeneficio $concesion, string $estado, string $motivo): void
    {
        if (! $concesion) {
            return;
        }

        $concesion->estado = $estado;
        $concesion->motivo = $motivo;
        $concesion->save();
    }

    private function valorActual(Cliente $cliente, string $campo): ?string
    {
        return match ($campo) {
            SolicitudBeneficio::CAMPO_LISTA => $this->valor($cliente->lista_actual_id),
            SolicitudBeneficio::CAMPO_TIPO => $this->valor($cliente->catalogo_tipo_cliente_id),
            SolicitudBeneficio::CAMPO_VENDEDOR => $this->valor($cliente->vendedor_id),
            SolicitudBeneficio::CAMPO_MODALIDAD => $cliente->es_menudeo ? '1' : '0',
            default => null,
        };
    }

    private function valor(mixed $id): ?string
    {
        if ($id === null || $id === '') {
            return null;
        }

        return (string) $id;
    }

    private function mismoValor(?string $esperado, ?string $actual): bool
    {
        return $esperado === $actual;
    }

    private function recalcularListaCliente(Cliente $cliente): void
    {
        if ($cliente->es_menudeo) {
            return;
        }

        if ($cliente->lista_bloqueada) {
            return;
        }

        if ($this->autoridad->clienteGobernadoPorModulo($cliente)) {
            $listaId = $this->proyeccionCliente->listaVigenteId($cliente);
            if ($listaId) {
                $cliente->lista_actual_id = $listaId;
            }

            return;
        }

        $listas = CatalogoListaDescuento::with('porcentajeEscalonamiento')
            ->where('activo', true)
            ->orderByDesc('monto_requerido')
            ->get();

        $listaCalificada = $this->escalonamiento->resolverListaPorAcumuladoNeto((float) $cliente->monto_venta_actual, $listas);
        $cliente->lista_actual_id = $listaCalificada ? $listaCalificada->id : null;
    }

    private function aplicarConversionMenudeo(Cliente $cliente, SolicitudTag $solicitud, ClasificacionComercialCliente $clasificacion, ?string $listaAnterior): void
    {
        $lista = $solicitud->catalogo_lista_descuento_id
            ? CatalogoListaDescuento::query()->find($solicitud->catalogo_lista_descuento_id)
            : null;
        if (! $clasificacion->esListaMayorista($lista)) {
            $lista = $clasificacion->buscarBronce();
        }
        if ($lista && (int) $cliente->lista_actual_id !== (int) $lista->id) {
            $cliente->lista_actual_id = $lista->id;
            $this->conceder($cliente, $solicitud, SolicitudBeneficio::CAMPO_LISTA, $listaAnterior, $this->valor($cliente->lista_actual_id));
        } elseif ($lista) {
            $this->conceder($cliente, $solicitud, SolicitudBeneficio::CAMPO_LISTA, $listaAnterior, $this->valor($cliente->lista_actual_id));
        }

        $anterior = $cliente->es_menudeo ? '1' : '0';
        $usuarioId = Auth::id() ?: $solicitud->vendedor_id;
        $clasificacion->asignar(
            $cliente,
            false,
            ClasificacionComercialCliente::ORIGEN_CONVERSION,
            (int) $solicitud->id,
            $usuarioId ? (int) $usuarioId : null,
        );
        $this->conceder($cliente, $solicitud, SolicitudBeneficio::CAMPO_MODALIDAD, $anterior, '0');
        $clasificacion->registrarEvento($cliente, $usuarioId ? (int) $usuarioId : null, (int) $solicitud->id);
    }

    private function ratificarConversion(Cliente $cliente, SolicitudTag $solicitud, ClasificacionComercialCliente $clasificacion): void
    {
        $usuarioId = Auth::id() ?: $solicitud->vendedor_id;
        $clasificacion->asignar(
            $cliente,
            false,
            ClasificacionComercialCliente::ORIGEN_CONVERSION,
            (int) $solicitud->id,
            $usuarioId ? (int) $usuarioId : null,
        );
        $this->conceder($cliente, $solicitud, SolicitudBeneficio::CAMPO_MODALIDAD, '0', '0');
        $clasificacion->registrarEvento($cliente, $usuarioId ? (int) $usuarioId : null, (int) $solicitud->id);
    }

    private function solicitudAutorizada(SolicitudTag $solicitud): bool
    {
        $id = (int) $solicitud->catalogo_estado_solicitud_id;
        $respondida = (int) CatalogoEstadoSolicitud::idDe('Respondida');
        $verificada = (int) CatalogoEstadoSolicitud::idDe('Verificada');

        return $id > 0 && ($id === $respondida || $id === $verificada);
    }

    private function modalidadRatificada(Cliente $cliente, SolicitudTag $solicitud, SolicitudBeneficio $propio): bool
    {
        if ($cliente->conversion_solicitud_id && (int) $cliente->conversion_solicitud_id !== (int) $solicitud->id) {
            return true;
        }

        return ClienteModalidadEvento::query()
            ->where('cliente_id', $cliente->id)
            ->where('created_at', '>=', $propio->created_at)
            ->where(function ($consulta) use ($solicitud) {
                $consulta->whereNull('solicitud_id')->orWhere('solicitud_id', '!=', $solicitud->id);
            })
            ->exists();
    }

    private function solicitudAsignaTag(SolicitudTag $solicitud): bool
    {
        $nombreProceso = strtoupper($solicitud->proceso?->nombre ?? '');

        return str_contains($nombreProceso, 'ASIGNAR TAG')
            || str_contains($nombreProceso, 'ASIGNAR CLIENTE');
    }

    private function clienteDe(SolicitudTag $solicitud): ?Cliente
    {
        if (! $solicitud->cliente_id) {
            return null;
        }

        $solicitud->loadMissing(['cliente.listaDescuento', 'cliente.vendedor', 'cliente.tipo', 'proceso', 'vendedor']);

        return $solicitud->cliente;
    }
}
