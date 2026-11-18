<?php

namespace App\Services\ControlPedidos\Conversion;

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCumplimientoFisico;
use App\Models\ControlPedidos\PedidoBmaTareaHistorial;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\User;
use App\Services\ControlPedidos\AsegurarCumplimientoFisicoService;
use App\Services\ControlPedidos\CalcularRequisitosPreparacionService;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use App\Support\ControlPedidos\Conversion\FisicaConversionModalidad;
use App\Support\ControlPedidos\EstatusSucursalPedidoBma;
use App\Support\ControlPedidos\InstruccionEntrega;

class SustituirInstruccionAbiertaConversion
{
    /** @var list<string> */
    private const ESTADOS_MOVIMIENTO = [
        PedidoBmaTareaPreparacion::ESTADO_EN_TRASLADO,
        PedidoBmaTareaPreparacion::ESTADO_RECIBIDA_CEDIS,
        PedidoBmaTareaPreparacion::ESTADO_LIBERACION_SOLICITADA,
    ];

    public function __construct(
        private CalcularRequisitosPreparacionService $requisitos,
        private AsegurarCumplimientoFisicoService $asegurar,
    ) {}

    public function clasificar(PedidoBma $pedido): FisicaConversionModalidad
    {
        if ($this->enCustodiaOTransito($pedido)) {
            return new FisicaConversionModalidad(
                CodigosConversionModalidad::FISICA_CUSTODIA_EXCEPCION,
                true,
                'La mercancía permanece en sucursal. La entrega queda bloqueada hasta la adecuación física posterior.',
                true,
            );
        }
        $abierta = InstruccionEntrega::abiertas((int) $pedido->id)->first();
        if ($abierta && ! $abierta->modalidad?->esEnvioMunicipio()) {
            return new FisicaConversionModalidad(
                CodigosConversionModalidad::FISICA_INSTRUCCION_ABIERTA,
                true,
                'La instrucción de Tienda abierta se sustituye por envío municipal.',
            );
        }

        return new FisicaConversionModalidad(
            CodigosConversionModalidad::FISICA_SIN_MOVIMIENTO,
            false,
        );
    }

    public function ejecutar(PedidoBma $pedido, User $usuario, string $motivo): FisicaConversionModalidad
    {
        $fisica = $this->clasificar($pedido);
        if ($fisica->condicion === CodigosConversionModalidad::FISICA_CUSTODIA_EXCEPCION) {
            ResguardoPdv::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
                ->update(['entrega_bloqueada' => true]);

            return $fisica;
        }
        if ($fisica->condicion !== CodigosConversionModalidad::FISICA_INSTRUCCION_ABIERTA) {
            return $fisica;
        }

        $abiertas = InstruccionEntrega::abiertas((int) $pedido->id);
        if ($abiertas->count() > 1) {
            throw ConversionModalidadException::noElegible(InstruccionEntrega::MENSAJE_AMBIGUA);
        }
        $tarea = $abiertas->first();
        if (! $tarea) {
            return $fisica;
        }
        $tarea->loadMissing(['modalidad', 'productos', 'cumplimientoFisico']);
        if (in_array($tarea->estado, self::ESTADOS_MOVIMIENTO, true)) {
            ResguardoPdv::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
                ->update(['entrega_bloqueada' => true]);

            return new FisicaConversionModalidad(
                CodigosConversionModalidad::FISICA_CUSTODIA_EXCEPCION,
                true,
                'La mercancía ya tiene un movimiento físico. La entrega queda bloqueada hasta la adecuación posterior.',
                true,
            );
        }

        $modalidad = CatalogoModalidadPreparacionPedido::query()
            ->where('codigo', CatalogoModalidadPreparacionPedido::CODIGO_ENVIO_MUNICIPIO)
            ->where('activo', true)
            ->first();
        if (! $modalidad) {
            throw ConversionModalidadException::noElegible('Falta configurar la modalidad de envío municipal.');
        }

        $clave = hash('sha256', implode('|', ['conversion-sustituir', $pedido->id, $tarea->id, $modalidad->id]));
        $repetida = PedidoBmaTareaPreparacion::query()->where('idempotencia_clave', $clave)->first();
        if ($repetida) {
            return $fisica;
        }

        $estadoAnterior = $tarea->estado;
        $tarea->update([
            'estado' => PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA,
            'version' => (int) $tarea->version + 1,
        ]);
        $nueva = PedidoBmaTareaPreparacion::query()->create([
            'pedido_bma_id' => $pedido->id,
            'catalogo_modalidad_preparacion_id' => $modalidad->id,
            'almacen_id' => $tarea->almacen_id,
            'area_responsable_codigo' => 'TIENDA',
            'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            'solicitada_por_id' => $usuario->id,
            'solicitada_at' => now(),
            'fecha_limite' => $this->requisitos->calcularFechaLimite($modalidad),
            'observaciones_solicitud' => $motivo,
            'tarea_anterior_id' => $tarea->id,
            'idempotencia_clave' => $clave,
            'requiere_traslado_cedis' => $modalidad->requiereTrasladoCedisPorDefecto(),
        ]);
        PedidoBmaTareaHistorial::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'usuario_id' => $usuario->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => PedidoBmaTareaPreparacion::ESTADO_SUSTITUIDA,
            'accion' => 'sustituir_instruccion',
            'comentario' => $motivo,
            'meta_json' => [
                'modalidad_anterior' => $tarea->modalidad?->codigo,
                'modalidad_nueva' => $modalidad->codigo,
                'instruccion_nueva_id' => $nueva->id,
                'origen' => 'conversion_modalidad',
            ],
        ]);
        $fisico = $tarea->cumplimientoFisico;
        if ($fisico) {
            PedidoBmaCumplimientoFisico::query()->whereKey($fisico->id)->lockForUpdate()->first()
                ?->update(['pedido_bma_tarea_preparacion_id' => $nueva->id]);
        } else {
            $this->asegurar->ejecutar($nueva);
        }
        foreach ($tarea->productos as $producto) {
            $nueva->productos()->create([
                'producto_id' => $producto->producto_id,
                'sku' => $producto->sku,
                'descripcion_snapshot' => $producto->descripcion_snapshot,
                'cantidad_solicitada' => $producto->cantidad_solicitada,
                'orden' => $producto->orden,
            ]);
        }

        return $fisica;
    }

    private function enCustodiaOTransito(PedidoBma $pedido): bool
    {
        $estatus = (string) ($pedido->estatus_sucursal ?? '');
        if (in_array($estatus, [
            EstatusSucursalPedidoBma::EN_CUSTODIA,
            EstatusSucursalPedidoBma::EN_TRANSITO,
            EstatusSucursalPedidoBma::EN_RECEPCION,
            EstatusSucursalPedidoBma::CUSTODIA_PARCIAL,
        ], true)) {
            return true;
        }

        return ResguardoPdv::query()
            ->where('pedido_bma_id', $pedido->id)
            ->where('estado', ResguardoPdv::ESTADO_EN_CUSTODIA)
            ->exists();
    }
}
