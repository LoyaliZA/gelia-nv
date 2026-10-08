<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\BultosEsperadosResguardoPdv;
use App\Support\PuntoVenta\Resguardos\EvidenciaMinimaRegistroManualPdv;
use App\Support\PuntoVenta\Resguardos\EstadoResguardoPdv;
use App\Support\PuntoVenta\Resguardos\GeneradorCodigoEtiquetaResguardoPdv;
use App\Support\PuntoVenta\Resguardos\RutaAlmacenamientoResguardoPdv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class IngresarResguardoManualPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
        private readonly SincronizarEstatusSucursalPedidoBmaService $sincronizarEstatusSucursal,
        private readonly NotificarResguardoPdvService $notificar,
    ) {}

    /**
     * Cierra llegada y pase a recepción en el mismo gesto cuando ya existe la evidencia mínima.
     * El llamador debe estar dentro de la transacción que creó o adjuntó esa evidencia.
     */
    public function completar(ResguardoPdv $resguardo, User $actor, string $claveBase): ResguardoPdv
    {
        $resguardo->load('evidencias');

        if (! EvidenciaMinimaRegistroManualPdv::esManual($resguardo)) {
            return $resguardo;
        }

        if ($resguardo->estado !== ResguardoPdv::ESTADO_PENDIENTE_RECEPCION) {
            return $resguardo;
        }

        if (! EvidenciaMinimaRegistroManualPdv::completa($resguardo)) {
            return $resguardo;
        }

        $bultos = BultosEsperadosResguardoPdv::desdeResguardo($resguardo);
        $ahora = now();
        $esperada = (int) $resguardo->cantidad_bultos_esperada;

        foreach ($bultos as $dato) {
            ResguardoPdvBulto::query()->create([
                'resguardo_id' => $resguardo->id,
                'pedido_bma_id' => $resguardo->pedido_bma_id,
                'folio' => $dato['folio'],
                'codigo_etiqueta' => GeneradorCodigoEtiquetaResguardoPdv::generar(),
                'tipo' => $dato['tipo'],
                'piezas' => $dato['piezas'],
                'estado' => ResguardoPdvBulto::ESTADO_RECIBIDO_GERENTE,
                'recepcion_at' => $ahora,
                'recepcion_por_id' => $actor->id,
                'version' => 1,
            ]);
        }

        $resguardo->update([
            'estado' => ResguardoPdv::ESTADO_RECIBIDO,
            'recepcion_fisica_at' => $resguardo->recepcion_fisica_at ?? $ahora,
            'version' => $resguardo->version + 1,
        ]);

        $this->registrarEvento($resguardo, $actor, $ahora, ResguardoPdvEvento::TIPO_RECEPCION_COMPLETA, ResguardoPdv::ESTADO_PENDIENTE_RECEPCION, ResguardoPdv::ESTADO_RECIBIDO, $this->claveDerivada($claveBase, 'rec'), [
            'paso' => 'registro_manual',
            'bultos' => $bultos,
            'cantidad_llegada' => count($bultos),
            'cantidad_recibida' => count($bultos),
            'cantidad_esperada' => $esperada,
            'cantidad_pendiente' => 0,
            'recepcion_completa' => true,
            'evidencia_reutilizada' => true,
        ]);

        return $this->pasarARecepcion($resguardo->fresh(['bultos']), $actor, $claveBase, $ahora);
    }

    /**
     * Si la confirmación de llegada es de un alta manual con evidencia mínima,
     * el pase a recepción ocurre en el mismo paso.
     */
    public function cerrarConfirmacionDoble(ResguardoPdv $resguardo, User $actor, string $claveBase): ResguardoPdv
    {
        if (! EvidenciaMinimaRegistroManualPdv::esManual($resguardo)) {
            return $resguardo;
        }

        if ($resguardo->estado !== ResguardoPdv::ESTADO_RECIBIDO) {
            return $resguardo;
        }

        return $this->pasarARecepcion($resguardo, $actor, $claveBase, now());
    }

    /**
     * @param  array{archivo_ticket?: UploadedFile|null, foto_paquete?: UploadedFile|null, idempotency_key: string}  $datos
     */
    public function adjuntar(ResguardoPdv $resguardo, User $actor, array $datos): ResguardoPdv
    {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_REGISTRAR_MANUAL,
            (int) $resguardo->sucursal_id
        );

        $clave = (string) $datos['idempotency_key'];
        $pathsEscritos = [];

        try {
            return DB::transaction(function () use ($resguardo, $actor, $datos, $clave, &$pathsEscritos) {
                $resguardo = ResguardoPdv::query()->lockForUpdate()->findOrFail($resguardo->id);
                $reintento = $this->resguardoPorClave($resguardo, $clave);
                if ($reintento !== null) {
                    return $reintento;
                }

                if (! EvidenciaMinimaRegistroManualPdv::esManual($resguardo)) {
                    throw ValidationException::withMessages([
                        'resguardo' => 'Este resguardo no proviene de un registro manual.',
                    ]);
                }

                if (! in_array($resguardo->estado, [
                    ResguardoPdv::ESTADO_PENDIENTE_RECEPCION,
                    ResguardoPdv::ESTADO_RECIBIDO,
                ], true)) {
                    throw ValidationException::withMessages([
                        'estado' => 'La evidencia de este resguardo ya cubre el ingreso a recepción.',
                    ]);
                }

                $evento = ResguardoPdvEvento::query()->create([
                    'resguardo_id' => $resguardo->id,
                    'tipo_evento' => ResguardoPdvEvento::TIPO_REGISTRO_MANUAL_EVIDENCIA,
                    'estado_anterior' => $resguardo->estado,
                    'estado_nuevo' => $resguardo->estado,
                    'actor_id' => $actor->id,
                    'ocurrido_at' => now(),
                    'snapshot_json' => [
                        'handoff' => CrearResguardoManualPdvService::HANDOFF,
                    ],
                    'idempotency_key' => $clave,
                ]);

                $this->guardarSiFalta(
                    $resguardo,
                    $evento,
                    $datos['archivo_ticket'] ?? null,
                    ResguardoPdvEvidencia::USO_TICKET,
                    $actor->id,
                    $pathsEscritos
                );
                $this->guardarSiFalta(
                    $resguardo,
                    $evento,
                    $datos['foto_paquete'] ?? null,
                    ResguardoPdvEvidencia::USO_PAQUETE,
                    $actor->id,
                    $pathsEscritos
                );

                $resguardo->unsetRelation('evidencias');
                if (! EvidenciaMinimaRegistroManualPdv::completa($resguardo)) {
                    return $resguardo->fresh(['bultos', 'evidencias']);
                }

                if ($resguardo->estado === ResguardoPdv::ESTADO_PENDIENTE_RECEPCION) {
                    return $this->completar($resguardo, $actor, $clave);
                }

                return $this->cerrarConfirmacionDoble($resguardo, $actor, $clave);
            });
        } catch (UniqueConstraintViolationException $e) {
            $this->eliminarArchivosHuerfanos($pathsEscritos);
            $recuperado = $this->resguardoPorClave($resguardo, $clave);
            if ($recuperado !== null) {
                return $recuperado;
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->eliminarArchivosHuerfanos($pathsEscritos);
            throw $e;
        }
    }

    /**
     * @param  list<string>  $pathsEscritos
     */
    public function guardar(
        ResguardoPdv $resguardo,
        ResguardoPdvEvento $evento,
        UploadedFile $archivo,
        string $uso,
        int $actorId,
        array &$pathsEscritos,
    ): void {
        $ruta = $archivo->store(RutaAlmacenamientoResguardoPdv::prefijo($resguardo, 'registro-manual'), 'local');
        $pathsEscritos[] = $ruta;

        $mime = (string) $archivo->getMimeType();
        $esPdf = str_contains(strtolower($mime), 'pdf')
            || strtolower((string) $archivo->getClientOriginalExtension()) === 'pdf';

        ResguardoPdvEvidencia::query()->create([
            'resguardo_id' => $resguardo->id,
            'evento_id' => $evento->id,
            'tipo' => $esPdf ? ResguardoPdvEvidencia::TIPO_ARCHIVO : ResguardoPdvEvidencia::TIPO_FOTO,
            'ruta_interna' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $mime,
            'tamano_bytes' => $archivo->getSize(),
            'hash_sha256' => hash_file('sha256', $archivo->getRealPath() ?: $archivo->getPathname()),
            'actor_id' => $actorId,
            'capturado_at' => now(),
            'inmutable' => true,
            'metadata_json' => [
                'origen' => 'registro_manual',
                'uso' => $uso,
            ],
        ]);
    }

    private function pasarARecepcion(ResguardoPdv $resguardo, User $actor, string $claveBase, Carbon $ahora): ResguardoPdv
    {
        $estadoAnterior = $resguardo->estado;
        $resguardo->update([
            'estado' => ResguardoPdv::ESTADO_EN_RECEPCION,
            'version' => $resguardo->version + 1,
        ]);

        $this->registrarEvento(
            $resguardo,
            $actor,
            $ahora,
            ResguardoPdvEvento::TIPO_PASADO_A_RECEPCION,
            $estadoAnterior,
            ResguardoPdv::ESTADO_EN_RECEPCION,
            $this->claveDerivada($claveBase, 'pas'),
            [
                'paso' => 'registro_manual',
                'cantidad_bultos' => EstadoResguardoPdv::cantidadRecibidaGerente($resguardo->fresh('bultos')),
                'evidencia_reutilizada' => true,
            ]
        );

        $resguardo = $resguardo->fresh(['bultos', 'almacen', 'evidencias']);
        $this->sincronizarEstatusSucursal->desdeResguardo($resguardo);
        $this->notificar->pasadoARecepcion($resguardo, (int) $resguardo->sucursal_id, $this->claveDerivada($claveBase, 'pas'));

        return $resguardo;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function registrarEvento(
        ResguardoPdv $resguardo,
        User $actor,
        Carbon $ahora,
        string $tipo,
        ?string $estadoAnterior,
        string $estadoNuevo,
        string $clave,
        array $snapshot,
    ): void {
        ResguardoPdvEvento::query()->create([
            'resguardo_id' => $resguardo->id,
            'tipo_evento' => $tipo,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'actor_id' => $actor->id,
            'ocurrido_at' => $ahora,
            'snapshot_json' => $snapshot,
            'idempotency_key' => $clave,
        ]);
    }

    /**
     * @param  list<string>  $pathsEscritos
     */
    private function guardarSiFalta(
        ResguardoPdv $resguardo,
        ResguardoPdvEvento $evento,
        ?UploadedFile $archivo,
        string $uso,
        int $actorId,
        array &$pathsEscritos,
    ): void {
        if (! $archivo instanceof UploadedFile) {
            return;
        }

        if (in_array($uso, EvidenciaMinimaRegistroManualPdv::usosPresentes($resguardo), true)) {
            return;
        }

        $this->guardar($resguardo, $evento, $archivo, $uso, $actorId, $pathsEscritos);
        $resguardo->unsetRelation('evidencias');
    }

    private function resguardoPorClave(ResguardoPdv $resguardo, string $clave): ?ResguardoPdv
    {
        $evento = ResguardoPdvEvento::query()->where('idempotency_key', $clave)->first();
        if ($evento === null) {
            return null;
        }

        if ((int) $evento->resguardo_id !== (int) $resguardo->id) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        return $resguardo->fresh(['bultos', 'evidencias', 'almacen']);
    }

    private function claveDerivada(string $base, string $sufijo): string
    {
        return substr($sufijo.':'.hash('sha256', $base), 0, 64);
    }

    /**
     * @param  list<string>  $pathsEscritos
     */
    private function eliminarArchivosHuerfanos(array $pathsEscritos): void
    {
        foreach ($pathsEscritos as $ruta) {
            Storage::disk('local')->delete($ruta);
        }
    }
}
