<?php

namespace App\Services\ControlPedidos\Municipal;

use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\PersistenciaAdjuntosConsulta;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\Municipal\CambioMunicipalATienda;
use App\Support\ControlPedidos\Municipal\ComplementosMunicipales;
use App\Support\ControlPedidos\Municipal\RecorridoFisicoMunicipal;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ventas solicita que el cliente recoja en sucursal un envío municipal con compra ya registrada. Solo registra la
 * solicitud y detiene la salida a terminal: no mueve mercancía, no cambia el destino físico ni libera pagos o remisión.
 * Un destino distinto del previsto sigue la regla de cambio de sucursal: antes del primer traslado se actualiza la
 * instrucción; con el traslado hecho necesita autorización de gerencia y un nuevo traslado, que esta solicitud no crea.
 */
final class SolicitarCambioMunicipalATiendaService
{
    public const MAX_MOTIVO = 200;

    public function __construct(
        private PersistenciaAdjuntosConsulta $persistencia,
        private RegistrarHistorialPedidoService $historial,
        private ResolverSucursalDestinoMunicipal $sucursales,
    ) {}

    /**
     * @param  array{solicitud_id: string, motivo: string, error_captura?: bool, sucursal_destino_id?: int|string|null, evidencias?: list<UploadedFile>}  $datos
     */
    public function ejecutar(PedidoBma $pedido, User $usuario, array $datos): PedidoBma
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            throw ValidationException::withMessages(['permiso' => 'Quien registró el pedido solicita el cambio a recolección en sucursal.']);
        }
        $solicitudId = trim((string) ($datos['solicitud_id'] ?? ''));
        $motivo = trim((string) ($datos['motivo'] ?? ''));
        $errorCaptura = (bool) ($datos['error_captura'] ?? false);
        $archivos = array_values(array_filter($datos['evidencias'] ?? [], fn ($a) => $a instanceof UploadedFile));
        if ($solicitudId === '') {
            throw ValidationException::withMessages(['solicitud_id' => 'Falta la clave de la solicitud.']);
        }
        if ($motivo === '' || mb_strlen($motivo) > self::MAX_MOTIVO) {
            throw ValidationException::withMessages(['motivo' => 'Indique el motivo (hasta '.self::MAX_MOTIVO.' caracteres).']);
        }
        if (! $errorCaptura && $archivos === []) {
            throw ValidationException::withMessages(['evidencias' => 'Adjunte la evidencia de la petición del cliente o indique que se notificó un error de captura.']);
        }

        $rutas = [];
        try {
            return DB::transaction(function () use ($pedido, $usuario, $solicitudId, $motivo, $errorCaptura, $archivos, $datos, &$rutas) {
                $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
                $previo = CambioMunicipalATienda::datos($pedido);
                if (($previo['solicitud_id'] ?? null) === $solicitudId) {
                    return $pedido;
                }
                $this->assertAdmite($pedido);

                $ubicacion = IntegrarComplementoMunicipalService::ubicacion($pedido);
                $previsto = $pedido->sucursal_destino_id ? (int) $pedido->sucursal_destino_id : null;
                $pedida = ($datos['sucursal_destino_id'] ?? null) ? (int) $datos['sucursal_destino_id'] : null;
                // Sin otra sucursal se conserva la prevista; solo una distinta se valida contra las habilitadas.
                $pedidoDestino = ($pedida === null || $pedida === $previsto)
                    ? (int) $previsto
                    : $this->sucursales->ejecutar($pedida);
                $cambiaDestino = $previsto !== null && $pedidoDestino !== $previsto;
                // Con el primer traslado ya hecho, otra sucursal exige autorización de gerencia y un nuevo traslado.
                $requiereGerencia = $cambiaDestino && $ubicacion !== IntegrarComplementoMunicipalService::UBICACION_CEDIS;

                $documentos = [];
                if ($archivos !== []) {
                    $docs = $this->persistencia->guardar($pedido, $archivos, [
                        'carpeta' => 'cambio_a_tienda',
                        'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                        'comentario' => 'Petición del cliente: recolección en sucursal',
                        'relacion_tipo' => CambioMunicipalATienda::RELACION_EVIDENCIA,
                    ]);
                    $rutas = array_column($docs, 'ruta');
                    $documentos = array_column($docs, 'id');
                }

                $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
                $json[CambioMunicipalATienda::CLAVE] = [
                    'estado' => CambioMunicipalATienda::ESTADO_SOLICITADO,
                    'solicitud_id' => $solicitudId,
                    'motivo' => $motivo,
                    'error_captura' => $errorCaptura,
                    'documentos_ids' => $documentos,
                    'solicitado_por_id' => $usuario->id,
                    'solicitado_at' => now()->toIso8601String(),
                    'ubicacion_al_solicitar' => $ubicacion,
                    'sucursal_prevista_id' => $previsto,
                    'sucursal_destino_id' => $pedidoDestino,
                    'requiere_autorizacion_gerencia' => $requiereGerencia,
                ];
                $pedido->update(['solicitud_preparacion_json' => $json]);

                $estatusId = (int) $pedido->catalogo_estatus_pedido_id;
                $this->historial->ejecutar(
                    $pedido->id,
                    $usuario->id,
                    $estatusId,
                    $estatusId,
                    'Se solicitó que el cliente recoja en sucursal'.($errorCaptura ? ' (se notificó un error de captura)' : '').". Motivo: {$motivo}. El paquete no sale a terminal.",
                    'cambio_a_tienda_solicitado'
                );

                return $pedido->fresh();
            });
        } catch (\Throwable $e) {
            $this->persistencia->limpiar($rutas);
            throw $e;
        }
    }

    private function assertAdmite(PedidoBma $pedido): void
    {
        if (! RecorridoFisicoMunicipal::aplica($pedido)) {
            throw ValidationException::withMessages(['pedido' => 'Este envío no sigue el recorrido municipal por sucursal; su cambio lo gestiona Control de pedidos.']);
        }
        if ($pedido->cancelado_at) {
            throw ValidationException::withMessages(['pedido' => 'El pedido está cancelado.']);
        }
        if (RecorridoFisicoMunicipal::envioYaSalioParaAjustes($pedido)) {
            throw ValidationException::withMessages(['pedido' => RecorridoFisicoMunicipal::MENSAJE_SALIO_A_TERMINAL]);
        }
        if (CambioMunicipalATienda::activo($pedido)) {
            throw ValidationException::withMessages(['pedido' => 'Ya hay un cambio a recolección en sucursal para este envío.']);
        }
        if (! $pedido->pedidoPagadoORemisionado()) {
            throw ValidationException::withMessages(['pedido' => 'Sin pago ni remisión, el pedido se modifica con «Modificar pedido».']);
        }
        if ($pedido->esComplemento()) {
            throw ValidationException::withMessages(['pedido' => 'Las piezas adicionales siguen el destino de su envío principal.']);
        }
        if (ComplementosMunicipales::hayAbiertos($pedido)) {
            throw ValidationException::withMessages(['pedido' => 'Integre o resuelva las piezas adicionales antes de cambiar a recolección en sucursal.']);
        }
    }
}
