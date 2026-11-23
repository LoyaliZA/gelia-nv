<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\CatalogoEstatusPedido;
use App\Models\ControlPedidos\CatalogoTipoOperacionEnvio;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\GenerarFolioPedidoBmaService;
use App\Services\ControlPedidos\PersistenciaAdjuntosConsulta;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\ConsultaMunicipal;
use App\Support\ControlPedidos\Municipal\ComplementosMunicipales;
use App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * M-03b. Con remisión y antes de la salida a terminal, Ventas agrega piezas con un pedido complementario propio.
 * Hereda cliente, destino, terminal y consignatario del principal; su importe, pago y remisión son propios.
 * No toca remisión ni pago del principal. Todo ocurre con el principal bloqueado, en el mismo orden que la salida a terminal.
 */
final class RegistrarComplementoMunicipalService
{
    public function __construct(
        private GenerarFolioPedidoBmaService $folios,
        private RegistrarHistorialPedidoService $historial,
        private PersistenciaAdjuntosConsulta $persistencia,
    ) {}

    /**
     * @param  array{solicitud_id: string, folio_remision: string, total_mercancia: float|int|string, piezas: list<array{descripcion: string, cantidad: int}>, evidencias: list<UploadedFile>}  $datos
     */
    public function ejecutar(PedidoBma $principal, User $usuario, array $datos): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $principal)) {
            throw ValidationException::withMessages(['permiso' => 'Quien registró el pedido agrega las piezas adicionales.']);
        }
        $solicitudId = trim((string) ($datos['solicitud_id'] ?? ''));
        $folioPedido = trim((string) ($datos['folio_remision'] ?? ''));
        $importe = round((float) ($datos['total_mercancia'] ?? 0), 2);
        $piezas = $this->piezas($datos['piezas'] ?? []);
        $archivos = array_values(array_filter($datos['evidencias'] ?? [], fn ($a) => $a instanceof UploadedFile));
        if ($solicitudId === '') {
            throw ValidationException::withMessages(['solicitud_id' => 'Falta la clave de la solicitud.']);
        }
        if ($folioPedido === '') {
            throw ValidationException::withMessages(['folio_remision' => 'Indique el folio del pedido de las piezas adicionales.']);
        }
        if ($importe <= 0) {
            throw ValidationException::withMessages(['total_mercancia' => 'Indique el importe de las piezas adicionales.']);
        }
        if ($piezas === []) {
            throw ValidationException::withMessages(['piezas' => 'Indique las piezas adicionales.']);
        }
        if ($archivos === []) {
            throw ValidationException::withMessages(['evidencias' => 'Adjunte el documento o la captura de las piezas adicionales.']);
        }

        $rutas = [];
        try {
            return DB::transaction(function () use ($principal, $usuario, $solicitudId, $folioPedido, $importe, $piezas, $archivos, &$rutas) {
                $principal = PedidoBma::query()->lockForUpdate()->findOrFail($principal->id);
                $previo = $this->previo($principal, $solicitudId);
                if ($previo) {
                    return $previo;
                }
                $this->assertAdmite($principal);

                $borrador = CatalogoEstatusPedido::porFase(CatalogoEstatusPedido::FASE_BORRADOR);
                if (! $borrador) {
                    throw new \RuntimeException('No se encontró el estatus de borrador.');
                }
                $hijo = PedidoBma::query()->create([
                    'folio' => $this->folios->ejecutarComplemento($principal),
                    'folio_remision' => mb_substr($folioPedido, 0, 60),
                    'fecha' => now()->toDateString(),
                    'vendedor_id' => $principal->vendedor_id,
                    'cliente_id' => $principal->cliente_id,
                    'departamento_operacion_id' => $principal->departamento_operacion_id,
                    'origen_id' => $principal->origen_id,
                    'almacen_id' => $principal->almacen_id,
                    'sucursal_destino_id' => $principal->sucursal_destino_id,
                    'catalogo_paqueteria_id' => $principal->catalogo_paqueteria_id,
                    'catalogo_estatus_pedido_id' => $borrador->id,
                    'tipo_operacion_envio_id' => CatalogoTipoOperacionEnvio::idNormal(),
                    'pedido_principal_id' => $principal->id,
                    'total_mercancia' => $importe,
                    'costo_envio' => 0,
                    'saldo_a_favor' => 0,
                    'costo_seguro' => 0,
                    'total_a_cobrar' => $importe,
                    'aplica_seguro' => false,
                    'es_resguardo' => false,
                    'prioridad_md' => false,
                    'solicitud_preparacion_json' => [
                        'version' => 1,
                        ComplementosMunicipales::CLAVE => [
                            'estado' => ComplementosMunicipales::ESTADO_ABIERTO,
                            'pedido_origen_id' => $principal->id,
                            'solicitud_id' => $solicitudId,
                            'solicitado_por_id' => $usuario->id,
                            'solicitado_at' => now()->toIso8601String(),
                            'piezas' => $piezas,
                            // El principal pudo haber sido trasladado a PDV; la ubicación se decide al integrar.
                            'version_principal' => (int) ($principal->revision_registro ?? 0),
                        ],
                    ],
                ]);
                $docs = $this->persistencia->guardar($hijo, $archivos, [
                    'carpeta' => 'complemento',
                    'tipo' => PedidoBmaDocumento::TIPO_ANEXO_PIEZAS,
                    'comentario' => 'Piezas adicionales del envío '.$principal->folio,
                ]);
                $rutas = array_column($docs, 'ruta');

                $json = is_array($principal->solicitud_preparacion_json) ? $principal->solicitud_preparacion_json : [];
                $lista = is_array($json[ComplementosMunicipales::CLAVE_PRINCIPAL] ?? null) ? array_values($json[ComplementosMunicipales::CLAVE_PRINCIPAL]) : [];
                $lista[] = ['solicitud_id' => $solicitudId, 'pedido_id' => $hijo->id, 'folio' => $hijo->folio];
                $json[ComplementosMunicipales::CLAVE_PRINCIPAL] = $lista;
                $principal->update(['solicitud_preparacion_json' => $json]);

                $estatusId = (int) $principal->catalogo_estatus_pedido_id;
                $this->historial->ejecutar(
                    $principal->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    "Piezas adicionales solicitadas en el pedido complementario {$hijo->folio}. El envío no sale a terminal hasta integrarlas.",
                    'complemento_municipal_solicitado'
                );

                return $hijo->fresh(['documentos', 'estatus']);
            });
        } catch (\Throwable $e) {
            $this->persistencia->limpiar($rutas);
            throw $e;
        }
    }

    /** Reintento de la misma solicitud: devuelve el complemento ya creado. */
    private function previo(PedidoBma $principal, string $solicitudId): ?PedidoBma
    {
        $json = is_array($principal->solicitud_preparacion_json) ? $principal->solicitud_preparacion_json : [];
        foreach ((array) ($json[ComplementosMunicipales::CLAVE_PRINCIPAL] ?? []) as $item) {
            if (is_array($item) && (string) ($item['solicitud_id'] ?? '') === $solicitudId) {
                return PedidoBma::query()->find((int) ($item['pedido_id'] ?? 0));
            }
        }

        return null;
    }

    private function assertAdmite(PedidoBma $principal): void
    {
        if (! ConsultaMunicipal::es($principal) || $principal->esComplemento()) {
            throw ValidationException::withMessages(['modalidad' => 'Solo un envío municipal principal admite piezas adicionales.']);
        }
        if ($principal->cancelado_at) {
            throw ValidationException::withMessages(['pedido' => 'El pedido está cancelado.']);
        }
        if (RecorridoFisicoMunicipal::envioYaSalioParaAjustes($principal)) {
            throw ValidationException::withMessages(['anexo' => RecorridoFisicoMunicipal::MENSAJE_ANEXO_SALIO]);
        }
        if (! $principal->tieneRemision() && ! filled($principal->numero_remision)) {
            throw ValidationException::withMessages(['anexo' => 'Sin remisión se modifica el pedido original con «Modificar pedido».']);
        }
    }

    /**
     * @param  array<int, mixed>  $piezas
     * @return list<array{descripcion: string, cantidad: int}>
     */
    private function piezas(array $piezas): array
    {
        $salida = [];
        foreach ($piezas as $pieza) {
            $descripcion = trim((string) (is_array($pieza) ? ($pieza['descripcion'] ?? '') : ''));
            if ($descripcion === '') {
                continue;
            }
            $salida[] = ['descripcion' => mb_substr($descripcion, 0, 180), 'cantidad' => max(1, (int) ($pieza['cantidad'] ?? 1))];
        }

        return $salida;
    }
}
