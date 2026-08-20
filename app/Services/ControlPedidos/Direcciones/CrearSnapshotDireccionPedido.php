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

            if (! $pedido->cliente_direccion_id) {
                $vigente = PedidoBmaDireccion::query()
                    ->where('pedido_bma_id', $pedido->id)
                    ->where('es_vigente', true)
                    ->first();

                if ($vigente
                    && $vigente->origen === PedidoBmaDireccion::ORIGEN_MANUAL
                    && (trim((string) $vigente->calle) !== '' || trim((string) $vigente->referencias) !== '')) {
                    return $vigente;
                }
            }

            $version = $this->siguienteVersion($pedido->id);
            $this->desactivarVigentes($pedido->id);

            if ($pedido->cliente_direccion_id) {
                $datos = $this->gestionDirecciones->obtenerParaSnapshot(
                    (int) $pedido->cliente_id,
                    (int) $pedido->cliente_direccion_id
                );

                $snapshot = PedidoBmaDireccion::query()->create([
                    'pedido_bma_id' => $pedido->id,
                    'cliente_direccion_id' => $datos['cliente_direccion_id'],
                    'version_snapshot' => $version,
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
                    'domicilio_irregular' => (bool) ($datos['domicilio_irregular'] ?? false),
                    'domicilio_legacy' => FormatearDireccionEstructurada::ejecutar($datos),
                    'origen' => PedidoBmaDireccion::ORIGEN_NORMALIZADO,
                ]);
            } else {
                $snapshot = PedidoBmaDireccion::query()->create([
                    'pedido_bma_id' => $pedido->id,
                    'cliente_direccion_id' => null,
                    'version_snapshot' => $version,
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

            return $this->proyectarEnPedido($pedido, $snapshot);
        });
    }

    /**
     * @param  array<string, mixed>  $campos
     */
    public function ejecutarManual(PedidoBma $pedido, array $campos, ?int $usuarioId = null, ?string $motivo = null): ?PedidoBmaDireccion
    {
        $calle = trim((string) ($campos['calle'] ?? ''));
        $refs = trim((string) ($campos['referencias'] ?? ''));
        $nombre = trim((string) ($campos['nombre_destinatario'] ?? ''));
        if ($calle === '' && $refs === '' && $nombre === '') {
            return null;
        }

        return DB::transaction(function () use ($pedido, $campos, $usuarioId, $motivo, $nombre) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $version = $this->siguienteVersion($pedido->id);
            $this->desactivarVigentes($pedido->id);

            $snapshot = PedidoBmaDireccion::query()->create([
                'pedido_bma_id' => $pedido->id,
                'cliente_direccion_id' => null,
                'version_snapshot' => $version,
                'es_vigente' => true,
                'motivo_cambio' => $motivo,
                'cambiado_por' => $usuarioId,
                'cambiado_en' => now(),
                'nombre_destinatario' => $nombre !== '' ? $nombre : ($pedido->envia_otra_persona ?: 'Destinatario no especificado'),
                'telefono_destinatario' => $campos['telefono_destinatario'] ?? null,
                'calle' => $campos['calle'] ?? null,
                'numero_exterior' => $campos['numero_exterior'] ?? null,
                'numero_interior' => $campos['numero_interior'] ?? null,
                'colonia' => $campos['colonia'] ?? null,
                'codigo_postal' => $campos['codigo_postal'] ?? null,
                'municipio' => $campos['municipio'] ?? null,
                'ciudad' => $campos['ciudad'] ?? null,
                'estado' => $campos['estado'] ?? null,
                'pais' => ($campos['pais'] ?? '') !== '' ? $campos['pais'] : 'México',
                'referencias' => $campos['referencias'] ?? null,
                'indicaciones_entrega' => $campos['indicaciones_entrega'] ?? null,
                'domicilio_irregular' => (bool) ($campos['domicilio_irregular'] ?? false),
                'domicilio_legacy' => FormatearDireccionEstructurada::ejecutar($campos),
                'origen' => PedidoBmaDireccion::ORIGEN_MANUAL,
            ]);

            $pedido->cliente_direccion_id = null;
            $pedido->save();

            return $this->proyectarEnPedido($pedido, $snapshot);
        });
    }

    public static function manualEstaCompleta(?PedidoBmaDireccion $snapshot): bool
    {
        if (! $snapshot || $snapshot->origen !== PedidoBmaDireccion::ORIGEN_MANUAL) {
            return false;
        }

        $nombre = trim((string) $snapshot->nombre_destinatario);
        $estado = trim((string) $snapshot->estado);
        if ($nombre === '' || $estado === '') {
            return false;
        }

        if ($snapshot->domicilio_irregular) {
            $muni = trim((string) $snapshot->municipio);
            $ciudad = trim((string) $snapshot->ciudad);
            $refs = trim((string) $snapshot->referencias);

            return $refs !== '' && ($muni !== '' || $ciudad !== '');
        }

        return trim((string) $snapshot->calle) !== ''
            && trim((string) $snapshot->colonia) !== ''
            && preg_match('/^\d{5}$/', (string) $snapshot->codigo_postal)
            && trim((string) $snapshot->municipio) !== '';
    }

    private function siguienteVersion(int $pedidoId): int
    {
        return max(1, (int) PedidoBmaDireccion::query()
            ->where('pedido_bma_id', $pedidoId)
            ->max('version_snapshot') + 1);
    }

    private function desactivarVigentes(int $pedidoId): void
    {
        PedidoBmaDireccion::query()
            ->where('pedido_bma_id', $pedidoId)
            ->where('es_vigente', true)
            ->update(['es_vigente' => false]);
    }

    private function proyectarEnPedido(PedidoBma $pedido, PedidoBmaDireccion $snapshot): PedidoBmaDireccion
    {
        $texto = FormatearDireccionPedido::desdeSnapshot($snapshot);
        $pedido->update([
            'domicilio_entrega' => $texto ?? $pedido->domicilio_entrega,
            'codigo_postal' => $snapshot->codigo_postal ?: $pedido->codigo_postal,
        ]);

        return $snapshot->fresh();
    }
}
