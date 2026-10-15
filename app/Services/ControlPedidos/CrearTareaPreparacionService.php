<?php

namespace App\Services\ControlPedidos;

use App\Models\Almacen;
use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Models\ControlPedidos\CatalogoPaqueteriaPedido;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaCaratula;
use App\Models\ControlPedidos\PedidoBmaTareaPreparacion;
use App\Models\ControlPedidos\PedidoBmaTareaProducto;
use App\Models\User;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\AlmacenPdv;
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
        private AsegurarCumplimientoFisicoService $asegurarCumplimiento,
        private ValidarAlmacenSalidaPedido $validarAlmacenSalida,
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
        array $meta = [],
        ?array $baseCaptura = null,
    ): PedidoBmaTareaPreparacion {
        $baseCaptura ??= ['revision_esperada' => (int) $pedido->revision_registro, 'base_captura' => $pedido->base_captura];
        $hash = \App\Support\ControlPedidos\HuellaSolicitudPedido::de([$pedido->id, $usuario->id, $codigoModalidad, $almacenId, $observaciones, $entregaMunicipal, $meta]);
        try {
        return DB::transaction(function () use ($pedido, $usuario, $codigoModalidad, $almacenId, $observaciones, $idempotenciaClave, $entregaMunicipal, $meta, $baseCaptura, $hash) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if (!VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) throw new \RuntimeException('No tiene permiso para solicitar preparación en este pedido.');
            if ($idempotenciaClave) {
                $existente = PedidoBmaTareaPreparacion::where('idempotencia_clave', $idempotenciaClave)->first();
                if ($existente) {
                    if ((int) $existente->pedido_bma_id !== (int) $pedido->id || !hash_equals((string) $existente->solicitud_hash, $hash)) throw new \App\Exceptions\ControlPedidos\ConflictoCapturaPedido('La clave de preparación corresponde a otra solicitud.');
                    return $existente;
                }
            }
            if ($baseCaptura !== null) \App\Support\ControlPedidos\RevisionCapturaPedido::comprobar($pedido, $baseCaptura);
            $tarea = $this->crear($pedido, $usuario, $codigoModalidad, $almacenId, $observaciones, $idempotenciaClave, $entregaMunicipal, $meta);
            $tarea->forceFill(['solicitud_hash' => $hash])->save();
            return $tarea;
        }, 3);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            throw new \App\Exceptions\ControlPedidos\ConflictoCapturaPedido('La clave de preparación ya está en uso. Revise la solicitud.');
        }
    }

    private function crear(
        PedidoBma $pedido,
        User $usuario,
        string $codigoModalidad,
        int $almacenId,
        ?string $observaciones = null,
        ?string $idempotenciaClave = null,
        array $entregaMunicipal = [],
        array $meta = [],
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

        $this->validarAlmacenSalida->ejecutar($almacenId, $pedido->almacen_id);

        if (! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw new \RuntimeException('No tiene permiso para solicitar preparación en este pedido.');
        }

        if (! $pedido->tieneSoporteSolicitud()) {
            throw ValidationException::withMessages([
                'pdf' => 'Debe adjuntar la cotización o el soporte del pedido antes de solicitar preparación.',
            ]);
        }

        $modalidad = CatalogoModalidadPreparacionPedido::query()
            ->where('codigo', $codigoModalidad)
            ->where('activo', true)
            ->firstOrFail();

        if ($modalidad->codigo === CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA) {
            $almacen = Almacen::query()->find($almacenId);
            if (! $almacen || ! AlmacenPdv::esAlmacen($almacen)) {
                throw ValidationException::withMessages([
                    'almacen_id' => 'Recoge hoy solo aplica para un almacén de tipo PDV.',
                ]);
            }
            $origenDepto = $this->origenDesdeDepartamentoPrincipal($usuario);
            if ($origenDepto !== null) {
                $meta['origen_solicitud'] = $origenDepto;
            }
        }

        app(ValidarSucursalDestinoPedidoBma::class)->ejecutar(
            $pedido,
            $pedido->sucursal_destino_id !== null ? (int) $pedido->sucursal_destino_id : null,
            $modalidad->codigo,
            $modalidad->esDestinoSucursal()
        );

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

        return DB::transaction(function () use ($pedido, $usuario, $modalidad, $almacenId, $observaciones, $idempotenciaClave, $datosMunicipio, $meta) {
            $pedido->loadMissing('estatus');
            $fechaLimite = $this->requisitosService->calcularFechaLimite($modalidad);
            $prioridadMd = (bool) ($meta['prioridad_md'] ?? false);
            if ($modalidad->codigo === CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA) {
                $prioridadMd = true;
                $fechaLimite = \App\Support\ControlPedidos\PlazosApartadoFisico::cierreOperativo(
                    now(), $this->config->zonaHoraria(), $this->config->cierreOperativoHora()
                );
            }
            if ($prioridadMd) {
                $cierre = now()->endOfDay();
                if ($fechaLimite === null || $fechaLimite->greaterThan($cierre)) {
                    $fechaLimite = $cierre;
                }
            }

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

            $cumplimiento = $this->asegurarCumplimiento->ejecutar($tarea);
            if ($modalidad->codigo === CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA) {
                $cumplimiento->update(['vence_at' => $fechaLimite]);
            }

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
            $departamentoContexto = \App\Support\ControlPedidos\ContextoDepartamentoPedido::dePedido($pedido);
            $codigoDepartamento = $departamentoContexto ? \App\Models\Departamento::find($departamentoContexto)?->codigo : null;
            $origen = match ($codigoDepartamento) { 'BEL' => PedidoBma::ORIGEN_SOLICITUD_BELLAROMA, 'ARO' => PedidoBma::ORIGEN_SOLICITUD_AROMAS, default => '' };
            if ($origen !== '') {
                $pedidoUpdates['origen_solicitud'] = $origen;
            }
            if (! empty($meta['documento_inicial'])) {
                $pedidoUpdates['documento_inicial'] = $meta['documento_inicial'];
            }
            if (trim((string) ($meta['contacto_nombre'] ?? '')) !== '') {
                $pedidoUpdates['contacto_nombre_snapshot'] = trim((string) $meta['contacto_nombre']);
            }
            if (trim((string) ($meta['contacto_telefono'] ?? '')) !== '') {
                $pedidoUpdates['contacto_telefono_snapshot'] = trim((string) $meta['contacto_telefono']);
            }
            $pedidoUpdates['prioridad_md'] = $prioridadMd;
            $pedido->update($pedidoUpdates);

            $folioRef = trim((string) ($meta['folio_referencia'] ?? ''));
            $tipoRef = trim((string) ($meta['tipo_referencia'] ?? ''));
            if ($folioRef !== '' && $tipoRef !== '') {
                \App\Models\ControlPedidos\PedidoBmaReferencia::registrar(
                    $pedido,
                    $tipoRef,
                    $folioRef,
                    $usuario->id,
                );
            }

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

    private function origenDesdeDepartamentoPrincipal(User $usuario): ?string
    {
        $usuario->loadMissing('departamento');
        $nombre = mb_strtolower(trim((string) $usuario->departamento?->nombre));
        if ($nombre === '') {
            return null;
        }
        if (str_contains($nombre, 'bellaroma')) {
            return PedidoBma::ORIGEN_SOLICITUD_BELLAROMA;
        }
        if (str_contains($nombre, 'aroma')) {
            return PedidoBma::ORIGEN_SOLICITUD_AROMAS;
        }

        return null;
    }

    private function normalizarTelefono(string $raw): string
    {
        $t = preg_replace('/[^\d+extEXT\s\-]/', '', trim($raw)) ?? '';

        return trim(preg_replace('/\s+/', ' ', $t) ?? '');
    }

    /**
     * @param  list<array{sku?: string, descripcion?: string, cantidad?: int, producto_id?: int|null}>  $lineas
     */
    public function guardarDesglose(PedidoBmaTareaPreparacion $tarea, array $lineas): PedidoBmaTareaPreparacion
    {
        if (! in_array($tarea->estado, [
            PedidoBmaTareaPreparacion::ESTADO_PENDIENTE,
            PedidoBmaTareaPreparacion::ESTADO_EN_ATENCION,
        ], true)) {
            throw ValidationException::withMessages([
                'lineas' => 'El desglose solo se puede capturar antes de responder la tarea.',
            ]);
        }

        $this->aplicarLineas($tarea, $lineas);

        return $tarea->fresh('productos');
    }

    /**
     * @param  list<array{sku?: string, descripcion?: string, cantidad?: int, producto_id?: int|null}>  $lineas
     */
    private function sincronizarProductos(PedidoBma $pedido, PedidoBmaTareaPreparacion $tarea, array $lineas = []): void
    {
        if ($lineas !== []) {
            $this->aplicarLineas($tarea, $lineas);

            return;
        }

        $pedido->loadMissing('revisionesProducto');
        $conSku = $pedido->revisionesProducto->filter(fn ($rev) => trim((string) $rev->sku) !== '');
        $orden = 0;

        if ($conSku->isNotEmpty()) {
            foreach ($conSku as $rev) {
                PedidoBmaTareaProducto::query()->create([
                    'pedido_bma_tarea_preparacion_id' => $tarea->id,
                    'pedido_bma_origen_id' => $pedido->id,
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
            'pedido_bma_origen_id' => $pedido->id,
            'descripcion_snapshot' => "Piezas del pedido ({$cantidad})",
            'cantidad_solicitada' => $cantidad,
            'orden' => 0,
        ]);
    }

    /**
     * @param  list<array{sku?: string, descripcion?: string, cantidad?: int, producto_id?: int|null}>  $lineas
     */
    private function aplicarLineas(PedidoBmaTareaPreparacion $tarea, array $lineas): void
    {
        if ($lineas === []) {
            throw ValidationException::withMessages([
                'lineas' => 'Capture al menos una pieza con SKU y cantidad.',
            ]);
        }

        $tarea->loadMissing('pedido');
        $tarea->productos()->delete();
        $orden = 0;
        foreach ($lineas as $linea) {
            $sku = trim((string) ($linea['sku'] ?? ''));
            $cantidad = (int) ($linea['cantidad'] ?? 0);
            if ($sku === '' || $cantidad < 1) {
                throw ValidationException::withMessages([
                    'lineas' => 'Cada pieza necesita SKU y cantidad mayor a cero.',
                ]);
            }
            $descripcion = trim((string) ($linea['descripcion'] ?? ''));
            PedidoBmaTareaProducto::query()->create([
                'pedido_bma_tarea_preparacion_id' => $tarea->id,
                'pedido_bma_origen_id' => $tarea->pedido_bma_id,
                'producto_id' => $linea['producto_id'] ?? null,
                'sku' => $sku,
                'descripcion_snapshot' => $descripcion !== '' ? $descripcion : $sku,
                'cantidad_solicitada' => $cantidad,
                'orden' => $orden++,
            ]);
        }
    }
}
