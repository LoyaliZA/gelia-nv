<?php

namespace App\Services\ControlPedidos;

use App\Models\Cliente;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\Direcciones\CrearSnapshotDireccionPedido;
use App\Services\SaldosAFavor\SincronizarAplicacionesPedidoSafService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActualizarPedidoBmaService
{
    use ResuelveDatosPedidoBma;

    public function __construct(
        private SincronizarAplicacionesPedidoSafService $safPedido,
        private CrearSnapshotDireccionPedido $crearSnapshot,
        private ActualizarCostosCajasPedidoBmaService $actualizarCostosCajas,
        private CalcularTotalesEnvioPedidoService $totalesEnvio,
        private AsignarSucursalDestinoPedidoService $asignarDestino,
        private ValidarAlmacenSalidaPedido $validarAlmacenSalida,
        private PersistirIntencionPreparacionPedido $intencionPreparacion,
    ) {}

    public function ejecutar(PedidoBma $pedido, array $datos, int $usuarioId): PedidoBma
    {
        $datos['revision_esperada'] ??= (int) $pedido->revision_registro;
        $datos['base_captura'] ??= \App\Support\ControlPedidos\RevisionCapturaPedido::base($pedido);
        if (!$pedido->esEditablePorVendedora()) {
            throw new \RuntimeException('Este pedido no puede editarse en su estado actual.');
        }

        return DB::transaction(function () use ($pedido, $datos, $usuarioId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            \App\Support\ControlPedidos\RevisionCapturaPedido::comprobar($pedido, $datos);
            app(ListarPedidosBmaService::class)->asegurarAcceso($pedido, User::findOrFail($usuarioId));
            if (!$pedido->esEditablePorVendedora()) throw new \App\Exceptions\ControlPedidos\ConflictoCapturaPedido('El pedido ya no admite edición. Recargue para revisar su estado.');

            $clienteId = $pedido->cliente_id;
            if (!empty($datos['cliente_id'])) {
                $clienteId = (int) $datos['cliente_id'];
            } elseif (!empty($datos['numero_cliente'])) {
                $cliente = Cliente::where('numero_cliente', $datos['numero_cliente'])->first();
                if ($cliente) {
                    $clienteId = $cliente->id;
                }
            }

            $eraRechazado = $pedido->estatus?->fase_ciclo === 'RECHAZADO_VENDEDORA';

            $attrs = $this->atributosActualizacionParcial($pedido, $datos);

            // No borrar captura temprana si el form/autoguard manda vacío (sync CEDIS / reload).
            if (empty($attrs['origen_id']) && $pedido->origen_id) {
                $attrs['origen_id'] = $pedido->origen_id;
            }
            if (empty($attrs['almacen_id']) && $pedido->almacen_id) {
                $attrs['almacen_id'] = $pedido->almacen_id;
            }
            $this->validarAlmacenSalida->ejecutar($attrs['almacen_id'] ?? null, $pedido->almacen_id);
            if (empty($attrs['catalogo_paqueteria_id']) && $pedido->catalogo_paqueteria_id) {
                $attrs['catalogo_paqueteria_id'] = $pedido->catalogo_paqueteria_id;
            }

            // Peso/cajas vienen de CEDIS: la vendedora no los sobrescribe al editar.
            if ($pedido->tienePesajeRespondido()) {
                $attrs['peso_real_kg'] = $pedido->peso_real_kg;
                $attrs['peso_volumetrico_kg'] = $pedido->peso_volumetrico_kg;
                $attrs['peso_cobrado_guia_kg'] = $pedido->peso_cobrado_guia_kg;
                $attrs['catalogo_tipo_caja_id'] = $pedido->catalogo_tipo_caja_id;
                $attrs['numero_cajas'] = $pedido->numero_cajas;
            }

            // Solo tratar como desglose si hay montos reales; filas vacías no anulan costo legado.
            $lineasCosto = is_array($datos['cajas_costos'] ?? null)
                ? array_values(array_filter(
                    $datos['cajas_costos'],
                    fn ($l) => is_array($l) && $this->lineaCajaTieneMonto($l)
                ))
                : [];
            $tieneDesgloseCajas = $lineasCosto !== [];
            if ($tieneDesgloseCajas) {
                unset($attrs['costo_envio'], $attrs['costo_seguro'], $attrs['total_a_cobrar']);
            }

            // La cola de errores se avanza al reenviar (EnviarPedidoBmaService), no al editar.
            $pedido->update(array_merge(
                $attrs,
                [
                    'cliente_id' => $clienteId,
                    'motivo_rechazo' => $eraRechazado ? null : $pedido->motivo_rechazo,
                ]
            ));

            if (array_key_exists('sucursal_destino_id', $datos)) {
                $actor = User::query()->find($usuarioId);
                if ($actor) {
                    $dest = $datos['sucursal_destino_id'] !== null && $datos['sucursal_destino_id'] !== ''
                        ? (int) $datos['sucursal_destino_id']
                        : null;
                    $this->asignarDestino->ejecutar($pedido->fresh(['origen', 'tareaPreparacionVigente.modalidad']), $actor, $dest);
                }
            }

            if ($tieneDesgloseCajas) {
                $this->actualizarCostosCajas->ejecutar(
                    $pedido->fresh(['cajas', 'zona', 'estatus']),
                    $lineasCosto,
                    $usuarioId,
                    filter_var($datos['reabrir_pago_costos'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    isset($datos['motivo_reapertura_pago']) ? (string) $datos['motivo_reapertura_pago'] : null
                );
            } elseif ($pedido->fresh()->cajas()->activas()->get()->contains(fn ($c) => $c->tieneDesgloseCosto())) {
                $this->totalesEnvio->aplicarAlPedido($pedido->fresh(['cajas', 'zona']));
            }

            if (!empty($datos['documentos_eliminar']) && is_array($datos['documentos_eliminar'])) {
                $this->eliminarDocumentos($pedido, $datos['documentos_eliminar']);
            }

            if (!empty($datos['comprobantes'])) {
                $this->agregarDocumentos($pedido, $datos['comprobantes']);
            }

            $this->intencionPreparacion->ejecutar($pedido, $datos);
            app(ResguardosEnvioPedido::class)->aplicar($pedido->fresh(), $datos, $usuarioId);

            if (array_key_exists('saf_aplicaciones', $datos)) {
                $this->safPedido->reservarParaPedido($pedido->fresh(), $datos['saf_aplicaciones'] ?? [], $usuarioId);
            }

            if (filter_var($datos['direccion_manual_excepcion'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && is_array($datos['direccion'] ?? null)) {
                $this->crearSnapshot->ejecutarManual(
                    $pedido->fresh(),
                    $datos['direccion'],
                    $usuarioId,
                    $datos['motivo_direccion_manual'] ?? null
                );
            }

            $pedido->increment('revision_registro');
            return $pedido->fresh(['cliente', 'estatus', 'envioTienda', 'documentos', 'almacen', 'banco', 'cajas.tipoCaja', 'cajas.tipoGuia', 'direccionVigente', 'sucursalDestino']);
        });
    }

    /**
     * Solo escribe columnas cuya entrada vino en el request.
     * Los defaults de creación no borran costo, dirección ni SAF omitidos.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function atributosActualizacionParcial(PedidoBma $pedido, array $datos): array
    {
        $calculado = $this->atributosPedidoBase($this->sembrarDesdePedido($pedido, $datos));
        $attrs = [];
        $directos = [
            'folio_remision', 'fecha', 'origen_id', 'almacen_id', 'catalogo_tipo_caja_id', 'numero_cajas',
            'peso_real_kg', 'catalogo_paqueteria_id', 'catalogo_tipo_guia_id', 'catalogo_zona_id',
            'codigo_postal', 'domicilio_entrega', 'cliente_direccion_id', 'es_resguardo',
            'envio_solo_resguardos', 'notas_resguardos_externos',
            'cliente_proporciona_guia', 'envio_por_cobrar', 'anexar_remision', 'comentarios_drive',
        ];

        foreach ($directos as $columna) {
            if (array_key_exists($columna, $datos)) {
                $attrs[$columna] = $calculado[$columna] ?? null;
            }
        }

        if (array_key_exists('envia_a_otra_persona', $datos)) {
            $attrs['envia_a_otra_persona'] = $calculado['envia_a_otra_persona'] ?? false;
            $attrs['envia_otra_persona'] = $calculado['envia_otra_persona'] ?? null;
        }

        if (array_key_exists('origen_id', $datos)) {
            $attrs['catalogo_envio_tienda_id'] = $calculado['catalogo_envio_tienda_id'] ?? null;
            $attrs['envio_tienda_otro'] = $calculado['envio_tienda_otro'] ?? null;
        }

        $tocaFinanzas = array_intersect(array_keys($datos), [
            'total_mercancia', 'costo_envio', 'aplica_seguro', 'saldo_a_favor', 'aplica_saldo_favor',
            'saf_aplicaciones', 'catalogo_paqueteria_id', 'envio_por_cobrar', 'cliente_proporciona_guia',
            'es_resguardo', 'modo_resguardo', 'tipo_operacion_envio_id', 'catalogo_zona_id',
            'catalogo_tipo_guia_id', 'peso_real_kg', 'peso_cobrado_guia_kg', 'catalogo_tipo_caja_id',
            'numero_cajas', 'pedido_principal_id',
        ]) !== [];

        if ($tocaFinanzas) {
            foreach ([
                'tipo_operacion_envio_id', 'pedido_principal_id', 'total_mercancia', 'costo_envio',
                'aplica_seguro', 'costo_seguro', 'saldo_a_favor', 'total_a_cobrar',
                'peso_volumetrico_kg', 'peso_cobrado_guia_kg', 'numero_cajas', 'peso_real_kg',
                'es_resguardo',
            ] as $columna) {
                if (array_key_exists($columna, $calculado)) {
                    $attrs[$columna] = $calculado[$columna];
                }
            }
        }

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function sembrarDesdePedido(PedidoBma $pedido, array $datos): array
    {
        $semilla = [
            'folio_remision' => $pedido->folio_remision,
            'fecha' => $pedido->fecha?->toDateString(),
            'origen_id' => $pedido->origen_id,
            'tipo_operacion_envio_id' => $pedido->tipo_operacion_envio_id,
            'pedido_principal_id' => $pedido->pedido_principal_id,
            'almacen_id' => $pedido->almacen_id,
            'catalogo_tipo_caja_id' => $pedido->catalogo_tipo_caja_id,
            'numero_cajas' => $pedido->numero_cajas,
            'peso_real_kg' => $pedido->peso_real_kg,
            'peso_cobrado_guia_kg' => $pedido->peso_cobrado_guia_kg,
            'catalogo_paqueteria_id' => $pedido->catalogo_paqueteria_id,
            'catalogo_tipo_guia_id' => $pedido->catalogo_tipo_guia_id,
            'catalogo_zona_id' => $pedido->catalogo_zona_id,
            'codigo_postal' => $pedido->codigo_postal,
            'domicilio_entrega' => $pedido->domicilio_entrega,
            'cliente_direccion_id' => $pedido->cliente_direccion_id,
            'envia_a_otra_persona' => (bool) $pedido->envia_a_otra_persona,
            'envia_otra_persona' => $pedido->envia_otra_persona,
            'es_resguardo' => (bool) $pedido->es_resguardo,
            'envio_solo_resguardos' => (bool) $pedido->envio_solo_resguardos,
            'notas_resguardos_externos' => $pedido->notas_resguardos_externos,
            'anexar_remision' => (bool) $pedido->anexar_remision,
            'total_mercancia' => $pedido->total_mercancia,
            'costo_envio' => $pedido->costo_envio,
            'aplica_seguro' => (bool) $pedido->aplica_seguro,
            'cliente_proporciona_guia' => (bool) $pedido->cliente_proporciona_guia,
            'envio_por_cobrar' => (bool) $pedido->envio_por_cobrar,
            'saldo_a_favor' => $pedido->saldo_a_favor,
            'aplica_saldo_favor' => (float) $pedido->saldo_a_favor > 0,
            'comentarios_drive' => $pedido->comentarios_drive,
        ];

        foreach ($datos as $clave => $valor) {
            if (in_array($clave, ['solicitud_preparacion', 'prioridad_md', 'comprobantes', 'documentos_eliminar', 'enviar', 'pedido_id', 'updated_at_esperado', 'revision_esperada', 'base_captura', 'captura_clave'], true)) {
                continue;
            }
            $semilla[$clave] = $valor;
        }

        return $semilla;
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private function lineaCajaTieneMonto(array $linea): bool
    {
        foreach (['costo_envio', 'costo_seguro', 'costo_adicional'] as $campo) {
            if (($linea[$campo] ?? null) !== null && ($linea[$campo] ?? '') !== '') {
                return true;
            }
        }

        return isset($linea['concepto_adicional']) && trim((string) $linea['concepto_adicional']) !== '';
    }

    private function eliminarDocumentos(PedidoBma $pedido, array $ids): void
    {
        $documentos = PedidoBmaDocumento::where('pedido_bma_id', $pedido->id)
            ->whereIn('id', $ids)
            ->get();

        foreach ($documentos as $doc) {
            Storage::disk('public')->delete($doc->ruta_archivo);
            $doc->delete();
        }
    }

    private function agregarDocumentos(PedidoBma $pedido, array $archivos): void
    {
        $orden = (int) $pedido->documentos()->max('orden') + 1;

        foreach ($archivos as $archivo) {
            if (!$archivo instanceof UploadedFile || !$archivo->isValid()) {
                continue;
            }

            $ruta = $archivo->store("pedidos_bma/comprobantes/{$pedido->id}", 'public');

            $pedido->documentos()->create([
                'tipo' => PedidoBmaDocumento::TIPO_COMPROBANTE,
                'ruta_archivo' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'orden' => $orden++,
            ]);
        }
    }
}
