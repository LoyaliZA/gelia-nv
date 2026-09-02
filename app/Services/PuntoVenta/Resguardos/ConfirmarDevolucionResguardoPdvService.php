<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\DevolucionResguardoPdvConfirmada;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvBulto;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvEvidencia;
use App\Models\User;
use App\Services\PuntoVenta\PuntoVentaModulo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ConfirmarDevolucionResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @param  list<UploadedFile>  $evidencias
     */
    public function ejecutar(
        ResguardoPdv $resguardo,
        User $actor,
        int $versionEsperada,
        string $idempotencyKey,
        string $motivo,
        array $evidencias = [],
    ): ResguardoPdv {
        $this->alcance->asegurarMutacionPiso(
            $actor,
            PuntoVentaModulo::PERMISO_RESGUARDOS_CONFIRMAR_DEVOLUCION,
            (int) $resguardo->sucursal_id
        );

        $pathsEscritos = [];

        try {
            return DB::transaction(function () use (
                $resguardo,
                $actor,
                $versionEsperada,
                $idempotencyKey,
                $motivo,
                $evidencias,
                &$pathsEscritos,
            ) {
                $resguardo = ResguardoPdv::query()
                    ->with(['bultos'])
                    ->lockForUpdate()
                    ->findOrFail($resguardo->id);

                $reintento = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                if ($reintento !== null) {
                    return $reintento;
                }

                $this->assertVersionYEstado($resguardo, $versionEsperada);

                $bultosDevolver = $this->bultosEnCustodia($resguardo);
                $ahora = now();
                $estadoAnterior = $resguardo->estado;

                $snapshotEvento = [
                    'motivo' => $motivo,
                    'bultos' => $bultosDevolver->map(fn (ResguardoPdvBulto $bulto) => [
                        'id' => $bulto->id,
                        'folio' => $bulto->folio,
                        'tipo' => $bulto->tipo,
                    ])->values()->all(),
                    'cantidad_devuelta' => $bultosDevolver->count(),
                    'integracion_cp' => [
                        'estado' => 'pendiente',
                        'idempotency_key' => $idempotencyKey,
                        'intentos' => 0,
                    ],
                ];

                foreach ($bultosDevolver as $bulto) {
                    $bulto->update([
                        'estado' => ResguardoPdvBulto::ESTADO_DEVUELTO,
                        'devolucion_salida_at' => $ahora,
                        'version' => $bulto->version + 1,
                    ]);
                }

                $resguardo->update([
                    'estado' => ResguardoPdv::ESTADO_DEVUELTO,
                    'devolucion_confirmada_at' => $ahora,
                    'version' => $resguardo->version + 1,
                ]);

                try {
                    $evento = ResguardoPdvEvento::query()->create([
                        'resguardo_id' => $resguardo->id,
                        'tipo_evento' => ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA,
                        'estado_anterior' => $estadoAnterior,
                        'estado_nuevo' => ResguardoPdv::ESTADO_DEVUELTO,
                        'actor_id' => $actor->id,
                        'ocurrido_at' => $ahora,
                        'snapshot_json' => $snapshotEvento,
                        'idempotency_key' => $idempotencyKey,
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    $recuperado = $this->resolverReintentoIdempotente($resguardo, $idempotencyKey);
                    if ($recuperado !== null) {
                        return $recuperado;
                    }

                    throw $e;
                }

                $this->persistirEvidencias(
                    $resguardo,
                    $evento,
                    $evidencias,
                    $actor->id,
                    $ahora,
                    $pathsEscritos
                );

                $resguardo = $resguardo->fresh(['bultos']);

                DevolucionResguardoPdvConfirmada::dispatch(
                    $resguardo,
                    $evento,
                    $actor->id,
                    (int) $resguardo->sucursal_id
                );

                return $resguardo;
            });
        } catch (\Throwable $e) {
            $this->eliminarArchivosHuerfanos($pathsEscritos);
            throw $e;
        }
    }

    private function resolverReintentoIdempotente(ResguardoPdv $resguardo, string $idempotencyKey): ?ResguardoPdv
    {
        $evento = ResguardoPdvEvento::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $evento) {
            return null;
        }

        if ((int) $evento->resguardo_id !== (int) $resguardo->id) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        if ($evento->tipo_evento !== ResguardoPdvEvento::TIPO_DEVOLUCION_CONFIRMADA) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia ya fue utilizada en otra operación.',
            ]);
        }

        return $resguardo->fresh(['bultos']);
    }

    private function assertVersionYEstado(ResguardoPdv $resguardo, int $versionEsperada): void
    {
        if ((int) $resguardo->version !== $versionEsperada) {
            throw ValidationException::withMessages([
                'version' => 'Otro usuario modificó este resguardo. Actualice la página e intente de nuevo.',
            ]);
        }

        if ($resguardo->estado === ResguardoPdv::ESTADO_DEVUELTO) {
            throw new ConflictHttpException('Este resguardo ya fue devuelto.');
        }

        if ($resguardo->estado === ResguardoPdv::ESTADO_ENTREGADO) {
            throw new ConflictHttpException('Este resguardo ya fue entregado.');
        }

        if ($resguardo->estado !== ResguardoPdv::ESTADO_EN_CUSTODIA) {
            throw ValidationException::withMessages([
                'estado' => 'El resguardo no admite devolución desde su estado actual.',
            ]);
        }

        if ($this->bultosEnCustodia($resguardo)->isEmpty()) {
            throw ValidationException::withMessages([
                'bultos' => 'No hay bultos en custodia para devolver.',
            ]);
        }
    }

    /**
     * @return Collection<int, ResguardoPdvBulto>
     */
    private function bultosEnCustodia(ResguardoPdv $resguardo): Collection
    {
        return $resguardo->bultos
            ->filter(fn (ResguardoPdvBulto $bulto) => $bulto->estado === ResguardoPdvBulto::ESTADO_RECIBIDO)
            ->values();
    }

    /**
     * @param  list<UploadedFile>  $evidencias
     * @param  list<string>  $pathsEscritos
     */
    private function persistirEvidencias(
        ResguardoPdv $resguardo,
        ResguardoPdvEvento $evento,
        array $evidencias,
        int $actorId,
        \Illuminate\Support\Carbon $capturadoAt,
        array &$pathsEscritos,
    ): void {
        $archivos = array_values(array_filter(
            $evidencias,
            fn ($archivo) => $archivo instanceof UploadedFile && $archivo->isValid()
        ));

        foreach ($archivos as $archivo) {
            $ruta = $archivo->store("pdv/resguardos/{$resguardo->id}/devoluciones", 'local');
            $pathsEscritos[] = $ruta;

            ResguardoPdvEvidencia::query()->create([
                'resguardo_id' => $resguardo->id,
                'evento_id' => $evento->id,
                'tipo' => ResguardoPdvEvidencia::TIPO_FOTO,
                'ruta_interna' => $ruta,
                'nombre_original' => $archivo->getClientOriginalName(),
                'mime_type' => $archivo->getMimeType(),
                'tamano_bytes' => $archivo->getSize(),
                'hash_sha256' => hash_file('sha256', $archivo->getRealPath()),
                'actor_id' => $actorId,
                'capturado_at' => $capturadoAt,
                'inmutable' => true,
                'metadata_json' => ['origen' => 'devolucion_fisica'],
            ]);
        }
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
