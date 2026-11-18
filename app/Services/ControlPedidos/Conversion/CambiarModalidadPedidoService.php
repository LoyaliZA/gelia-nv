<?php

namespace App\Services\ControlPedidos\Conversion;

use App\Events\PedidoBmaActualizado;
use App\Models\ControlPedidos\PedidoBma;
use App\Models\ControlPedidos\PedidoBmaDocumento;
use App\Models\User;
use App\Services\ControlPedidos\PersistenciaAdjuntosConsulta;
use App\Services\ControlPedidos\RegistrarHistorialPedidoService;
use App\Support\ControlPedidos\AccionesHistorialPedidoBma;
use App\Support\ControlPedidos\Conversion\CodigosConversionModalidad;
use App\Support\ControlPedidos\Conversion\ConversionModalidadEnCursoPedidoBma;
use App\Support\ControlPedidos\Conversion\FlagsConversionModalidad;
use App\Support\ControlPedidos\Conversion\ResultadoConversionModalidad;
use App\Support\ControlPedidos\Conversion\SolicitudConfirmarConversion;
use App\Support\ControlPedidos\VisibilidadPedidoBma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CambiarModalidadPedidoService
{
    public function __construct(
        private EvaluarElegibilidadConversion $elegibilidad,
        private ResolverIdempotenciaConversion $idempotencia,
        private ValidarCapturaMunicipal $captura,
        private ResolverHerenciaConsultaConversion $herencia,
        private PersistirConversionModalidad $persistir,
        private SustituirInstruccionAbiertaConversion $fisica,
        private PersistenciaAdjuntosConsulta $adjuntos,
        private RegistrarHistorialPedidoService $historial,
    ) {}

    public function flags(PedidoBma $pedido, User $usuario, array $captura = []): FlagsConversionModalidad
    {
        return $this->elegibilidad->flags($pedido, $usuario, $captura);
    }

    public function reservar(PedidoBma $pedido, User $usuario, string $instanciaId): array
    {
        $this->assertActor($usuario, $pedido);

        return DB::transaction(function () use ($pedido, $usuario, $instanciaId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $this->exigirElegible($pedido, $usuario);
            if (! ConversionModalidadEnCursoPedidoBma::adquirir((int) $pedido->id, (int) $usuario->id, $instanciaId)) {
                throw ConversionModalidadException::enCurso();
            }
            event(new PedidoBmaActualizado((int) $pedido->id, CodigosConversionModalidad::ACCION_RESERVA_ADQUIRIDA));

            return $this->respuestaReserva($pedido, $usuario);
        });
    }

    public function latido(PedidoBma $pedido, User $usuario, string $instanciaId): array
    {
        $this->assertActor($usuario, $pedido);

        return DB::transaction(function () use ($pedido, $usuario, $instanciaId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if (! ConversionModalidadEnCursoPedidoBma::renovar((int) $pedido->id, (int) $usuario->id, $instanciaId)) {
                throw ConversionModalidadException::enCurso();
            }

            return $this->respuestaReserva($pedido, $usuario);
        });
    }

    public function soltarReserva(PedidoBma $pedido, User $usuario, string $instanciaId): array
    {
        $this->assertActor($usuario, $pedido);

        return DB::transaction(function () use ($pedido, $usuario, $instanciaId) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            $solto = ConversionModalidadEnCursoPedidoBma::soltar((int) $pedido->id, (int) $usuario->id, $instanciaId);
            if ($solto) {
                event(new PedidoBmaActualizado((int) $pedido->id, CodigosConversionModalidad::ACCION_RESERVA_LIBERADA));
            }

            return [
                'ok' => $solto,
                'reserva_activa' => ConversionModalidadEnCursoPedidoBma::activa((int) $pedido->id),
                'reserva_usuario_id' => ConversionModalidadEnCursoPedidoBma::payload((int) $pedido->id)?->usuario_id,
                'ttl_segundos' => ConversionModalidadEnCursoPedidoBma::TTL_SEGUNDOS,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $captura
     */
    public function vistaPrevia(PedidoBma $pedido, User $usuario, string $destino, array $captura = []): FlagsConversionModalidad
    {
        $this->assertActor($usuario, $pedido);

        return $this->elegibilidad->flags($pedido, $usuario, $captura);
    }

    public function confirmar(PedidoBma $pedido, User $usuario, SolicitudConfirmarConversion $solicitud): ResultadoConversionModalidad
    {
        $this->assertActor($usuario, $pedido);
        $rutas = [];
        try {
            return DB::transaction(function () use ($pedido, $usuario, $solicitud, &$rutas) {
                $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
                $pedido->loadMissing(['estatus', 'origen', 'vendedor']);

                if ($this->idempotencia->confirmada($pedido, $solicitud->clave_idempotencia)) {
                    return $this->resultado($pedido->fresh(['estatus', 'documentos', 'historial']), $usuario);
                }

                $payload = ConversionModalidadEnCursoPedidoBma::payload((int) $pedido->id);
                if ($payload && ! ConversionModalidadEnCursoPedidoBma::esPropietaria($payload, (int) $usuario->id, $solicitud->instancia_id)) {
                    throw ConversionModalidadException::enCurso();
                }

                $elegible = $this->elegibilidad->paraDestino($pedido, $usuario, $solicitud->destino);
                if (! $elegible->permitido) {
                    if ($elegible->codigo === CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA) {
                        throw ConversionModalidadException::consultaYaCerrada();
                    }
                    if ($elegible->codigo === CodigosConversionModalidad::CODIGO_DESTINO_INACTIVO) {
                        throw ConversionModalidadException::destinoInactivo($elegible->motivo_bloqueo);
                    }
                    throw ConversionModalidadException::noElegible($elegible->motivo_bloqueo);
                }

                $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
                $version = (int) (($json['conversion_modalidad']['conversion_version'] ?? 0));
                if ($solicitud->conversion_version !== $version) {
                    throw ConversionModalidadException::versionObsoleta();
                }
                if ($solicitud->revision_esperada !== (int) $pedido->revision_registro) {
                    throw ConversionModalidadException::vistaAtrasada();
                }

                $captura = $this->captura->ejecutar($pedido, [
                    'catalogo_paqueteria_id' => $solicitud->catalogo_paqueteria_id,
                    'consignatario_id' => $solicitud->consignatario_id,
                    'almacen_id' => $solicitud->almacen_id,
                    'leyenda_terminal' => $solicitud->leyenda_terminal,
                    'costo_transporte' => $solicitud->costo_transporte,
                    'costo_envio' => $solicitud->costo_envio,
                ]);

                $archivos = array_values(array_filter(
                    $solicitud->evidencias,
                    static fn ($archivo) => $archivo instanceof UploadedFile
                ));
                if (! $solicitud->error_captura && $archivos === []) {
                    throw ValidationException::withMessages([
                        'evidencias' => 'Adjunte la petición del cliente o notifique un error de captura.',
                    ]);
                }
                $documentosIds = [];
                if ($archivos !== []) {
                    $guardados = $this->adjuntos->guardar($pedido, $archivos, [
                        'carpeta' => 'conversion',
                        'tipo' => PedidoBmaDocumento::TIPO_EVIDENCIA_CONDICION,
                        'comentario' => $solicitud->error_captura
                            ? 'Se notificó un error de captura'
                            : $solicitud->motivo,
                        'relacion_tipo' => CodigosConversionModalidad::RELACION_TIPO_EVIDENCIA,
                    ]);
                    $rutas = array_values(array_filter(array_map(
                        static fn (array $fila) => $fila['ruta'] ?? null,
                        $guardados
                    )));
                    $documentosIds = array_values(array_filter(array_map(
                        static fn (array $fila) => $fila['id'] ?? null,
                        $guardados
                    )));
                }

                $herencia = $this->herencia->clasificar($pedido, $solicitud->almacen_id);
                $this->persistir->confirmar($pedido, $solicitud, $captura, $herencia, (int) $usuario->id, $documentosIds);
                $pedido->refresh();
                $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
                $json = $this->herencia->aplicar($json, $herencia, (int) $usuario->id, $solicitud->almacen_id);
                $pedido->update(['solicitud_preparacion_json' => $json]);
                $this->fisica->ejecutar($pedido->fresh(), $usuario, $solicitud->motivo);

                $pedido->increment('revision_registro');
                $this->historial->ejecutar(
                    (int) $pedido->id,
                    (int) $usuario->id,
                    $pedido->catalogo_estatus_pedido_id,
                    (int) $pedido->catalogo_estatus_pedido_id,
                    AccionesHistorialPedidoBma::ETIQUETAS[AccionesHistorialPedidoBma::CAMBIO_MODALIDAD_CONVERSION]
                        ?? CodigosConversionModalidad::ETIQUETA_HISTORIAL,
                    AccionesHistorialPedidoBma::CAMBIO_MODALIDAD_CONVERSION
                );
                ConversionModalidadEnCursoPedidoBma::soltar((int) $pedido->id, (int) $usuario->id, $solicitud->instancia_id);
                event(new PedidoBmaActualizado((int) $pedido->id, CodigosConversionModalidad::ACCION_CONFIRMADA));

                return $this->resultado($pedido->fresh(['estatus', 'documentos', 'historial']), $usuario);
            });
        } catch (\Throwable $e) {
            $this->adjuntos->limpiar($rutas);
            throw $e;
        }
    }

    public function cancelar(PedidoBma $pedido, User $usuario, string $instanciaId, int $revisionEsperada): FlagsConversionModalidad
    {
        $this->assertActor($usuario, $pedido);

        return DB::transaction(function () use ($pedido, $usuario, $instanciaId, $revisionEsperada) {
            $pedido = PedidoBma::query()->lockForUpdate()->findOrFail($pedido->id);
            if ($revisionEsperada !== (int) $pedido->revision_registro) {
                throw ConversionModalidadException::vistaAtrasada();
            }
            $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
            $conversion = is_array($json['conversion_modalidad'] ?? null) ? $json['conversion_modalidad'] : [];
            if (($conversion['estado'] ?? null) === CodigosConversionModalidad::ESTADO_CONFIRMADA) {
                throw ConversionModalidadException::noElegible('El cambio de modalidad confirmado no se deshace.');
            }
            $t03 = is_array($json['cambio_modalidad_tienda'] ?? null) ? $json['cambio_modalidad_tienda'] : [];
            $hayBorrador = ($conversion['estado'] ?? null) === CodigosConversionModalidad::ESTADO_BORRADOR;
            $hayT03 = ($t03['estado'] ?? null) === CodigosConversionModalidad::ESTADO_T03_PENDIENTE;
            if (! $hayBorrador && ! $hayT03) {
                throw ConversionModalidadException::noElegible('No hay un borrador de cambio de modalidad para cancelar.');
            }
            if ($hayBorrador) {
                unset($json['conversion_modalidad']);
            }
            if ($hayT03) {
                unset($json['cambio_modalidad_tienda']);
            }
            $pedido->update(['solicitud_preparacion_json' => $json]);
            ConversionModalidadEnCursoPedidoBma::soltar((int) $pedido->id, (int) $usuario->id, $instanciaId);
            event(new PedidoBmaActualizado((int) $pedido->id, CodigosConversionModalidad::ACCION_RESERVA_LIBERADA));

            return $this->elegibilidad->flags($pedido->fresh(['estatus', 'historial']), $usuario);
        });
    }

    private function resultado(PedidoBma $pedido, User $usuario): ResultadoConversionModalidad
    {
        $flags = $this->elegibilidad->flags($pedido, $usuario);
        $json = is_array($pedido->solicitud_preparacion_json) ? $pedido->solicitud_preparacion_json : [];
        $herencia = is_array($json['conversion_modalidad']['herencia'] ?? null)
            ? $json['conversion_modalidad']['herencia']
            : ['caso' => $flags->herencia->caso, 'procedencia' => $flags->herencia->procedencia];

        return new ResultadoConversionModalidad(
            (int) $pedido->id,
            $pedido->folio,
            (string) ($json['modalidad_entrega'] ?? ''),
            (int) ($json['conversion_modalidad']['conversion_version'] ?? 0),
            (int) $pedido->revision_registro,
            (int) $pedido->historial()->max('id'),
            $herencia,
            $flags,
        );
    }

    private function exigirElegible(PedidoBma $pedido, User $usuario): void
    {
        $elegible = $this->elegibilidad->paraDestino($pedido, $usuario, CodigosConversionModalidad::DESTINO_MUNICIPAL);
        if ($elegible->permitido) {
            return;
        }
        if ($elegible->codigo === CodigosConversionModalidad::CODIGO_CONSULTA_YA_CERRADA) {
            throw ConversionModalidadException::consultaYaCerrada();
        }
        throw ConversionModalidadException::noElegible($elegible->motivo_bloqueo);
    }

    private function assertActor(User $usuario, PedidoBma $pedido): void
    {
        if (! $usuario->can('control_pedidos.crear') || ! VisibilidadPedidoBma::puedeMutarComoVendedora($usuario, $pedido)) {
            abort(403, 'No tiene autorización para modificar este pedido.');
        }
    }

    /** @return array{ok: bool, reserva_activa: bool, reserva_usuario_id: ?int, ttl_segundos: int} */
    private function respuestaReserva(PedidoBma $pedido, User $usuario): array
    {
        $payload = ConversionModalidadEnCursoPedidoBma::payload((int) $pedido->id);

        return [
            'ok' => true,
            'reserva_activa' => $payload !== null,
            'reserva_usuario_id' => $payload?->usuario_id ?? $usuario->id,
            'ttl_segundos' => ConversionModalidadEnCursoPedidoBma::TTL_SEGUNDOS,
        ];
    }
}
