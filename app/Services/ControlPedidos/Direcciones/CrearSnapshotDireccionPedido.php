<?php

namespace App\Services\ControlPedidos\Direcciones;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDireccion;
use App\Services\Clientes\Direcciones\GestionDireccionesClienteService;
use App\Support\Clientes\FormatearDireccionEstructurada;
use Illuminate\Support\Facades\DB;

class CrearSnapshotDireccionPedido
{
    public function __construct(
        private GestionDireccionesClienteService $gestionDirecciones,
    ) {}

    public function ejecutar(PedidoBma $pedido, ?int $usuarioId = null, ?string $motivo = null): PedidoBmaDireccion
    {
        return DB::transaction(function () use ($pedido, $usuarioId, $motivo) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);

            $version = (int) PedidoBmaDireccion::query()
                ->where('pedido_bma_id', $pedido->id)
                ->max('version_snapshot') + 1;

            PedidoBmaDireccion::query()
                ->where('pedido_bma_id', $pedido->id)
                ->where('es_vigente', true)
                ->update(['es_vigente' => false]);

            if ($pedido->cliente_direccion_id) {
                $datos = $this->gestionDirecciones->obtenerParaSnapshot(
                    (int) $pedido->cliente_id,
                    (int) $pedido->cliente_direccion_id
                );

                $snapshot = PedidoBmaDireccion::query()->create([
                    'pedido_bma_id' => $pedido->id,
                    'cliente_direccion_id' => $datos['cliente_direccion_id'],
                    'version_snapshot' => max(1, $version),
                    'es_vigente' => true,
                    'motivo_cambio' => $motivo,
                    'cambiado_por' => $usuarioId,
                    'cambiado_en' => $motivo ? now() : null,
                    'numero_direccion' => $datos['numero_direccion'],
                    'etiqueta' => $datos['etiqueta'],
                    'tipo_direccion' => $datos['tipo_direccion'],
                    'nombre_destinatario' => $datos['nombre_destinatario'],
                    'telefono_destinatario' => $datos['telefono_destinatario'],
                    'calle' => $datos['calle'],
                    'numero_exterior' => $datos['numero_exterior'],
                    'numero_interior' => $datos['numero_interior'],
                    'colonia' => $datos['colonia'],
                    'codigo_postal' => $datos['codigo_postal'],
                    'municipio' => $datos['municipio'],
                    'ciudad' => $datos['ciudad'],
                    'estado' => $datos['estado'],
                    'pais' => $datos['pais'],
                    'referencias' => $datos['referencias'],
                    'indicaciones_entrega' => $datos['indicaciones_entrega'],
                    'domicilio_legacy' => FormatearDireccionEstructurada::ejecutar($datos),
                    'origen' => PedidoBmaDireccion::ORIGEN_NORMALIZADO,
                ]);
            } else {
                $snapshot = PedidoBmaDireccion::query()->create([
                    'pedido_bma_id' => $pedido->id,
                    'cliente_direccion_id' => null,
                    'version_snapshot' => max(1, $version),
                    'es_vigente' => true,
                    'motivo_cambio' => $motivo,
                    'cambiado_por' => $usuarioId,
                    'cambiado_en' => $motivo ? now() : null,
                    'nombre_destinatario' => $pedido->envia_otra_persona ?: 'Destinatario no especificado',
                    'telefono_destinatario' => null,
                    'codigo_postal' => $pedido->codigo_postal,
                    'domicilio_legacy' => $pedido->domicilio_entrega,
                    'origen' => PedidoBmaDireccion::ORIGEN_LEGACY,
                ]);
            }

            $texto = FormatearDireccionPedido::desdeSnapshot($snapshot);
            $pedido->update([
                'domicilio_entrega' => $texto ?? $pedido->domicilio_entrega,
                'codigo_postal' => $snapshot->codigo_postal ?: $pedido->codigo_postal,
            ]);

            return $snapshot->fresh();
        });
    }
}
