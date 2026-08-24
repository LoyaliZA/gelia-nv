<?php

namespace App\Services\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\MaquinaEstadosPedidoBma;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearTareaPreparacionService
{
    public function __construct(
        private PreparacionTiendaConfig $config,
        private CalcularRequisitosPreparacionService $requisitosService,
        private RegistrarHistorialPedidoService $historialService,
        private NotificarPedidoBmaService $notificarService,
    ) {}

    /**
     * @param  array{
     *   destinatario_es_cliente?: bool,
     *   destinatario_nombre?: string,
     *   destinatario_telefono?: string,
     *   municipio_destino?: string,
     *   direccion_referencia?: ?string,
     *   catalogo_paqueteria_id?: int,
     *   modalidad_cobro?: string
     * }  $entregaMunicipal
     */
    public function ejecutar(
        PedidoBma $pedido,
        User $usuario,
        string $codigoModalidad,
        int $almacenId,
        ?string $observaciones = null,
        ?string $idempotenciaClave = null,
        array $entregaMunicipal = [],
    ): PedidoBmaTareaPreparacion {
        if (! $this->config->activo() || ! $this->config->usuarioHabilitado($usuario)) {
            throw ValidationException::withMessages([
                'modalidad' => 'La preparación en Tienda no está habilitada para su usuario.',
            ]);
        }

        if (! $this->config->modalidadPermitida($codigoModalidad)) {
            throw ValidationException::withMessages([
                'modalidad' => 'La modalidad seleccionada no está habilitada.',
            ]);
        }

        if (! $this->config->almacenPermitido($almacenId)) {
            throw ValidationException::withMessages([
                'almacen_id' => 'El almacén seleccionado no está habilitado para preparación en Tienda.',
            ]);
        }

        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw new \RuntimeException('No tiene permiso para solicitar preparación en este pedido.');
        }

        if (! $pedido->tienePdfPedido()) {
            throw ValidationException::withMessages([
                'pdf' => 'Debe adjuntar el PDF o una foto del pedido antes de solicitar preparación.',
            ]);
        }

        $modalidad = CatalogoModalidadPreparacionPedido::query()
            ->where('codigo', $codigoModalidad)
            ->where('activo', true)
            ->firstOrFail();

        $datosMunicipio = [];
        if ($modalidad->esEnvioMunicipio()) {
            if (! $usuario->can('control_pedidos.preparacion.destinatario')
                && ! $usuario->can('control_pedidos.preparacion.solicitar')) {
                throw ValidationException::withMessages([
                    'destinatario' => 'No tiene permiso para capturar destinatario municipal.',
                ]);
            }
            $datosMunicipio = $this->validarEntregaMunicipal($pedido, $entregaMunicipal);
        }

        if ($idempotenciaClave) {
            $existente = PedidoBmaTareaPreparacion::query()
                ->where('idempotencia_clave', $idempotenciaClave)
                ->first();
            if ($existente) {
                return $existente->load(['modalidad', 'almacen', 'productos', 'pedido.cliente', 'paqueteria']);
            }
        }

        $activa = $pedido->tareaPreparacionVigente()->first();
        if ($activa) {
            throw ValidationException::withMessages([
                'tarea' => 'Ya existe una solicitud de preparación activa para este pedido.',
            ]);
        }

        return DB::transaction(function () use ($pedido, $usuario, $modalidad, $almacenId, $observaciones, $idempotenciaClave, $datosMunicipio) {
            $pedido->loadMissing('estatus');
            $fechaLimite = $this->requisitosService->calcularFechaLimite($modalidad);

            $tarea = PedidoBmaTareaPreparacion::query()->create(array_merge([
                'pedido_bma_id' => $pedido->id,
                'catalogo_modalidad_preparacion_id' => $modalidad->id,
                'almacen_id' => $almacenId,
                'area_responsable_codigo' => 'TIENDA',
                'estado' => PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
                'solicitada_por_id' => $usuario->id,
                'solicitada_at' => now(),
                'fecha_limite' => $fechaLimite,
                'observaciones_solicitud' => $observaciones,
                'idempotencia_clave' => $idempotenciaClave,
                'requiere_traslado_cedis' => $modalidad->requiereTrasladoCedisPorDefecto(),
            ], $datosMunicipio));

            $this->sincronizarProductos($pedido, $tarea);

            $estatusAnterior = $pedido->estatus;
            $estatusNuevo = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE);
            if (! $estatusNuevo) {
                throw new \RuntimeException('No se encontró el estatus de consulta pendiente.');
            }

            MaquinaEstadosPedidoBma::assertTransicion(
                $estatusAnterior?->fase_ciclo,
                CatalogoEstatusPedido::FASE_PESAJE_PENDIENTE
            );

            $pedidoUpdates = [
                'catalogo_estatus_pedido_id' => $estatusNuevo->id,
                'almacen_id' => $almacenId,
                'estatus_envio' => PedidoBma::ESTATUS_ENVIO_PENDIENTE_PESAJE,
                'pesaje_solicitado_at' => now(),
                'pesaje_respondido_at' => null,
                'pesaje_respondido_por_id' => null,
                'consulta_cerrada_at' => null,
                'consulta_cerrada_por_id' => null,
                'consulta_actualizacion_pendiente' => false,
            ];
            if (! empty($datosMunicipio['catalogo_paqueteria_id'])) {
                $pedidoUpdates['catalogo_paqueteria_id'] = $datosMunicipio['catalogo_paqueteria_id'];
                $pedidoUpdates['envio_por_cobrar'] = ($datosMunicipio['modalidad_cobro'] ?? '') === PedidoBmaCaratula::COBRO_POR_COBRAR;
            }
            $pedido->update($pedidoUpdates);

            if ($modalidad->esTransferencia()) {
                $pedido->update(['es_resguardo' => true]);
            }

            $this->historialService->ejecutar(
                $pedido->id,
                $usuario->id,
                $estatusAnterior->id,
                $estatusNuevo->id,
                "Solicitud de preparación en Tienda ({$modalidad->nombre}).",
                AccionesHistorialPedidoBma::SOLICITUD_PREPARACION_TIENDA
            );

            $this->notificarService->ejecutar(
                $pedido->fresh(['cliente', 'vendedor']),
                'pedido_preparacion_tienda_nueva',
                "Nueva solicitud de preparación en Tienda ({$modalidad->nombre}).",
                ['control_pedidos.tienda.ver'],
                $usuario->id,
                false,
                ['url' => '/control-pedidos/tienda?tarea='.$tarea->id]
            );

            return $tarea->fresh(['modalidad', 'almacen', 'productos', 'pedido.cliente', 'paqueteria']);
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function validarEntregaMunicipal(PedidoBma $pedido, array $datos): array
    {
        $esCliente = (bool) ($datos['destinatario_es_cliente'] ?? true);
        $pedido->loadMissing('cliente');

        $nombre = trim((string) ($datos['destinatario_nombre'] ?? ''));
        $telefono = $this->normalizarTelefono((string) ($datos['destinatario_telefono'] ?? ''));
        $municipio = trim((string) ($datos['municipio_destino'] ?? ''));
        $referencia = trim((string) ($datos['direccion_referencia'] ?? ''));
        $paqueteriaId = (int) ($datos['catalogo_paqueteria_id'] ?? 0);
        $cobro = strtoupper(trim((string) ($datos['modalidad_cobro'] ?? PedidoBmaCaratula::COBRO_PAGADO)));

        if ($esCliente) {
            $nombre = $nombre !== '' ? $nombre : (string) ($pedido->cliente?->nombre_comercial ?: $pedido->cliente?->nombre ?: '');
            $telefono = $telefono !== '' ? $telefono : $this->normalizarTelefono((string) ($pedido->cliente?->telefono ?? ''));
        }

        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 255) {
            throw ValidationException::withMessages(['destinatario_nombre' => 'Indique el nombre del destinatario.']);
        }
        if ($telefono === '' || mb_strlen($telefono) > 40) {
            throw ValidationException::withMessages(['destinatario_telefono' => 'Indique un teléfono válido.']);
        }
        if (mb_strlen($municipio) < 2 || mb_strlen($municipio) > 255) {
            throw ValidationException::withMessages(['municipio_destino' => 'Indique el municipio o destino.']);
        }

        $paq = CatalogoPaqueteriaPedido::query()->find($paqueteriaId);
        if (! $paq || ! $paq->habilitadaParaEnvioMunicipio()) {
            throw ValidationException::withMessages([
                'catalogo_paqueteria_id' => 'Seleccione un transporte habilitado para envío a municipio.',
            ]);
        }

        $reglas = $paq->reglasMunicipio();
        $campos = $reglas['campos_destino_obligatorios'] ?? [];
        if (in_array('direccion', $campos, true) || in_array('direccion_referencia', $campos, true)) {
            if ($referencia === '') {
                throw ValidationException::withMessages([
                    'direccion_referencia' => 'La dirección o referencia es obligatoria para este transporte.',
                ]);
            }
        }

        if ($cobro === PedidoBmaCaratula::COBRO_POR_COBRAR && ! $reglas['permite_por_cobrar']) {
            throw ValidationException::withMessages([
                'modalidad_cobro' => 'Este transporte no permite envío por cobrar.',
            ]);
        }
        if (! in_array($cobro, [PedidoBmaCaratula::COBRO_PAGADO, PedidoBmaCaratula::COBRO_POR_COBRAR], true)) {
            throw ValidationException::withMessages(['modalidad_cobro' => 'Modalidad de cobro inválida.']);
        }

        return [
            'destinatario_es_cliente' => $esCliente,
            'destinatario_nombre' => $nombre,
            'destinatario_telefono' => $telefono,
            'municipio_destino' => $municipio,
            'direccion_referencia' => $referencia !== '' ? $referencia : null,
            'catalogo_paqueteria_id' => $paq->id,
            'modalidad_cobro' => $cobro,
        ];
    }

    private function normalizarTelefono(string $raw): string
    {
        $t = preg_replace('/[^\d+extEXT\s\-]/', '', trim($raw)) ?? '';

        return trim(preg_replace('/\s+/', ' ', $t) ?? '');
    }

    private function sincronizarProductos(PedidoBma $pedido, PedidoBmaTareaPreparacion $tarea): void
    {
        $pedido->loadMissing('revisionesProducto');
        $orden = 0;

        if ($pedido->revisionesProducto->isNotEmpty()) {
            foreach ($pedido->revisionesProducto as $rev) {
                PedidoBmaTareaProducto::query()->create([
                    'pedido_bma_tarea_preparacion_id' => $tarea->id,
                    'producto_id' => $rev->producto_id,
                    'sku' => $rev->sku,
                    'descripcion_snapshot' => $rev->descripcion_producto,
                    'cantidad_solicitada' => 1,
                    'orden' => $orden++,
                ]);
            }

            return;
        }

        $cantidad = max(1, (int) ($pedido->cantidad_piezas ?: 1));
        PedidoBmaTareaProducto::query()->create([
            'pedido_bma_tarea_preparacion_id' => $tarea->id,
            'descripcion_snapshot' => "Piezas del pedido ({$cantidad})",
            'cantidad_solicitada' => $cantidad,
            'orden' => 0,
        ]);
    }
}
