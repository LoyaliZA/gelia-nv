<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Contracts\PuntoVenta\ResuelveAlcancePdv;
use App\Events\PuntoVenta\IncidenciaResguardoPdvResuelta;
use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvEvento;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Support\PuntoVenta\Resguardos\IncidenciaResguardoPdv;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolverIncidenciaResguardoPdvService
{
    public function __construct(
        private readonly ResuelveAlcancePdv $alcance,
    ) {}

    /**
     * @return array{resguardo: ResguardoPdv, incidencia: ResguardoPdvIncidencia}
     */
    public function ejecutar(
        ResguardoPdv $resguardo,
        ResguardoPdvIncidencia $incidencia,
        User $actor,
        int $versionResguardoEsperada,
        int $versionIncidenciaEsperada,
        string $idempotencyKey,
        string $motivoResolucion,
    ): array {
        if ((int) $incidencia->resguardo_id !== (int) $resguardo->id) {
            throw ValidationException::withMessages([
                'incidencia' => 'La incidencia no pertenece a este resguardo.',
            ]);
        }

        $permiso = IncidenciaResguardoPdv::permisoResolucion($incidencia);
        if ($permiso === null) {
            throw ValidationException::withMessages([
                'incidencia' => 'Este tipo de incidencia no admite resolución.',
            ]);
        }

        $this->alcance->asegurarMutacionPiso(
            $actor,
            $permiso,
            (int) $resguardo->sucursal_id
        );

        return DB::transaction(function () use (
            $resguardo,
            $incidencia,
            $actor,
            $versionResguardoEsperada,
            $versionIncidenciaEsperada,
            $idempotencyKey,
            $motivoResolucion,
        ) {
            $resguardo = ResguardoPdv::query()->lockForUpdate()->findOrFail($resguardo->id);
            $incidencia = ResguardoPdvIncidencia::query()->lockForUpdate()->findOrFail($incidencia->id);

            $reintento = $this->resolverReintentoIdempotente($resguardo, $incidencia, $idempotencyKey);
            if ($reintento !== null) {
                return $reintento;
            }

            $this->assertVersionesYEstado($resguardo, $incidencia, $versionResguardoEsperada, $versionIncidenciaEsperada, $motivoResolucion);

            $ahora = now();
            $estadoResolucion = IncidenciaResguardoPdv::estadoResolucion($incidencia);
            $tipoEvento = IncidenciaResguardoPdv::tipoEventoResolucion($incidencia);
            $descripcionOriginal = $incidencia->descripcion;

            $incidencia->update([
                'estado' => $estadoResolucion,
                'autorizado_por_id' => $actor->id,
                'autorizado_at' => $ahora,
                'motivo_autorizacion' => $motivoResolucion,
                'version' => $incidencia->version + 1,
            ]);

            $resguardo->update([
                'version' => $resguardo->version + 1,
            ]);

            try {
                $evento = ResguardoPdvEvento::query()->create([
                    'resguardo_id' => $resguardo->id,
                    'bulto_id' => $incidencia->bulto_id,
                    'tipo_evento' => $tipoEvento,
                    'estado_anterior' => $resguardo->estado,
                    'estado_nuevo' => $resguardo->estado,
                    'actor_id' => $actor->id,
                    'ocurrido_at' => $ahora,
                    'snapshot_json' => [
                        'incidencia_id' => $incidencia->id,
                        'incidencia_tipo' => $incidencia->tipo,
                        'incidencia_estado_anterior' => ResguardoPdvIncidencia::ESTADO_ABIERTA,
                        'incidencia_estado_nuevo' => $estadoResolucion,
                        'descripcion_original' => $descripcionOriginal,
                        'motivo_resolucion' => $motivoResolucion,
                    ],
                    'idempotency_key' => $idempotencyKey,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                $recuperado = $this->resolverReintentoIdempotente($resguardo, $incidencia, $idempotencyKey);
                if ($recuperado !== null) {
                    return $recuperado;
                }

                throw $e;
            }

            $resguardo = $resguardo->fresh(['bultos', 'almacen']);
            $incidencia = $incidencia->fresh(['evidencias', 'reportadoPor', 'autorizadoPor']);

            IncidenciaResguardoPdvResuelta::dispatch(
                $resguardo,
                $incidencia,
                $evento,
                (int) $resguardo->sucursal_id,
                $actor->id,
            );

            return [
                'resguardo' => $resguardo,
                'incidencia' => $incidencia,
            ];
        });
    }

    /**
     * @return array{resguardo: ResguardoPdv, incidencia: ResguardoPdvIncidencia}|null
     */
    private function resolverReintentoIdempotente(
        ResguardoPdv $resguardo,
        ResguardoPdvIncidencia $incidencia,
        string $idempotencyKey,
    ): ?array {
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

        $incidenciaEvento = (int) ($evento->snapshot_json['incidencia_id'] ?? 0);
        if ($incidenciaEvento !== (int) $incidencia->id) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'La clave de idempotencia corresponde a otra transición.',
            ]);
        }

        return [
            'resguardo' => $resguardo->fresh(['bultos', 'almacen']),
            'incidencia' => $incidencia->fresh(['evidencias', 'reportadoPor', 'autorizadoPor']),
        ];
    }

    private function assertVersionesYEstado(
        ResguardoPdv $resguardo,
        ResguardoPdvIncidencia $incidencia,
        int $versionResguardoEsperada,
        int $versionIncidenciaEsperada,
        string $motivoResolucion,
    ): void {
        if ((int) $resguardo->version !== $versionResguardoEsperada) {
            throw ValidationException::withMessages([
                'version' => 'Otro usuario modificó este resguardo. Actualice la página e intente de nuevo.',
            ]);
        }

        if ((int) $incidencia->version !== $versionIncidenciaEsperada) {
            throw ValidationException::withMessages([
                'incidencia_version' => 'Otro usuario modificó esta incidencia. Actualice la página e intente de nuevo.',
            ]);
        }

        if (! IncidenciaResguardoPdv::admiteResolucion($incidencia)) {
            throw ValidationException::withMessages([
                'incidencia' => 'La incidencia ya fue resuelta o no admite resolución.',
            ]);
        }

        if (trim($motivoResolucion) === '') {
            throw ValidationException::withMessages([
                'motivo_resolucion' => 'El motivo de resolución es obligatorio.',
            ]);
        }
    }
}
