<?php

namespace App\Services\ControlPedidos;

use App\Models\Cliente;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
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
    ) {}

    public function ejecutar(PedidoBma $pedido, array $datos, int $usuarioId): PedidoBma
    {
        if (!$pedido->esEditablePorVendedora()) {
            throw new \RuntimeException('Este pedido no puede editarse en su estado actual.');
        }

        return DB::transaction(function () use ($pedido, $datos, $usuarioId) {
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

            $attrs = $this->atributosPedidoBase($datos);

            // No borrar tipo de pedido si el form/autoguard manda origen vacío.
            if (empty($attrs['origen_id']) && $pedido->origen_id) {
                $attrs['origen_id'] = $pedido->origen_id;
            }

            // Peso/cajas vienen de CEDIS: la vendedora no los sobrescribe al editar.
            if ($pedido->tienePesajeRespondido()) {
                $attrs['peso_real_kg'] = $pedido->peso_real_kg;
                $attrs['peso_volumetrico_kg'] = $pedido->peso_volumetrico_kg;
                $attrs['peso_cobrado_guia_kg'] = $pedido->peso_cobrado_guia_kg;
                $attrs['catalogo_tipo_caja_id'] = $pedido->catalogo_tipo_caja_id;
                $attrs['numero_cajas'] = $pedido->numero_cajas;
            }

            // Si hay desglose por caja, el total canónico lo escribe ActualizarCostos / CalcularTotales.
            $tieneDesgloseCajas = ! empty($datos['cajas_costos']) && is_array($datos['cajas_costos']);
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

            if ($tieneDesgloseCajas) {
                $this->actualizarCostosCajas->ejecutar(
                    $pedido->fresh(['cajas', 'zona', 'estatus']),
                    $datos['cajas_costos'],
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

            return $pedido->fresh(['cliente', 'estatus', 'envioTienda', 'documentos', 'almacen', 'banco', 'cajas.tipoCaja', 'cajas.tipoGuia', 'direccionVigente']);
        });
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
