<?php

namespace App\Services\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdv;
use App\Models\PuntoVenta\ResguardoPdvIncidencia;
use App\Models\User;
use App\Notifications\PuntoVenta\AlertaResguardoPdvNotification;
use App\Services\PuntoVenta\PuntoVentaModulo;
use App\Support\PuntoVenta\Resguardos\EtiquetasResguardoPdv;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotificarResguardoPdvService
{
    public function recepcionEsperada(ResguardoPdv $resguardo, int $sucursalId, string $idempotencyKey): void
    {
        $this->enviar(
            $resguardo,
            $sucursalId,
            $idempotencyKey,
            AlertaResguardoPdvNotification::TIPO_RECEPCION_ESPERADA,
            [
                PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
                PuntoVentaModulo::PERMISO_RESGUARDOS_RECIBIR,
            ],
            'Recepción esperada',
            "Llegada programada del resguardo {$this->folio($resguardo)} a sucursal."
        );
    }

    public function recepcionFisica(ResguardoPdv $resguardo, int $sucursalId, string $idempotencyKey): void
    {
        $this->enviar(
            $resguardo,
            $sucursalId,
            $idempotencyKey,
            AlertaResguardoPdvNotification::TIPO_RECEPCION_FISICA,
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            'Recepción física',
            "Resguardo {$this->folio($resguardo)} recibido y en custodia."
        );
    }

    public function incidencia(
        ResguardoPdv $resguardo,
        ResguardoPdvIncidencia $incidencia,
        int $sucursalId,
        string $idempotencyKey,
    ): void {
        $tipoEtiqueta = EtiquetasResguardoPdv::tiposIncidencia()[$incidencia->tipo] ?? 'Incidencia';

        $this->enviar(
            $resguardo,
            $sucursalId,
            $idempotencyKey,
            AlertaResguardoPdvNotification::TIPO_INCIDENCIA,
            $this->permisosIncidencia($incidencia->tipo),
            'Incidencia en resguardo',
            "{$tipoEtiqueta} registrada en resguardo {$this->folio($resguardo)}.",
            [
                'incidencia_id' => $incidencia->id,
                'incidencia_tipo' => $incidencia->tipo,
            ]
        );
    }

    public function entrega(ResguardoPdv $resguardo, int $sucursalId, string $idempotencyKey): void
    {
        $this->enviar(
            $resguardo,
            $sucursalId,
            $idempotencyKey,
            AlertaResguardoPdvNotification::TIPO_ENTREGA,
            [PuntoVentaModulo::PERMISO_RESGUARDOS_VER],
            'Entrega completada',
            "Resguardo {$this->folio($resguardo)} entregado al cliente."
        );
    }

    /**
     * @param  list<string>  $permisos
     * @param  array<string, mixed>  $extras
     */
    private function enviar(
        ResguardoPdv $resguardo,
        int $sucursalId,
        string $idempotencyKey,
        string $tipoAlerta,
        array $permisos,
        string $tituloBase,
        string $mensaje,
        array $extras = [],
    ): void {
        try {
            $claveNotificacion = $this->claveNotificacion($tipoAlerta, $idempotencyKey);
            $destinatarios = $this->resolverDestinatarios($sucursalId, $permisos);
            $destinatarios = $this->excluirDuplicados($destinatarios, $claveNotificacion);

            if ($destinatarios->isEmpty()) {
                return;
            }

            $folio = $this->folio($resguardo);
            $notificacion = new AlertaResguardoPdvNotification(
                $tipoAlerta,
                "{$tituloBase}: {$folio}",
                $mensaje,
                (int) $resguardo->id,
                $folio,
                $sucursalId,
                $claveNotificacion,
                $extras,
            );

            Notification::send($destinatarios, $notificacion);
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar hito de resguardo PDV', [
                'resguardo_id' => $resguardo->id,
                'tipo' => $tipoAlerta,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }
    }

    /**
     * @param  list<string>  $permisos
     * @return Collection<int, User>
     */
    private function resolverDestinatarios(int $sucursalId, array $permisos): Collection
    {
        if ($permisos === []) {
            return collect();
        }

        return User::permission($permisos)
            ->whereHas(
                'sucursalesOperables',
                fn ($query) => $query->where('sucursales.id', $sucursalId)
            )
            ->get()
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, User>  $destinatarios
     * @return Collection<int, User>
     */
    private function excluirDuplicados(Collection $destinatarios, string $idempotencyKey): Collection
    {
        return $destinatarios->filter(function (User $usuario) use ($idempotencyKey): bool {
            return ! $usuario->notifications()
                ->where('type', AlertaResguardoPdvNotification::class)
                ->where('data->idempotency_key', $idempotencyKey)
                ->exists();
        })->values();
    }

    /**
     * @return list<string>
     */
    private function permisosIncidencia(string $tipoIncidencia): array
    {
        $permisoEspecifico = match ($tipoIncidencia) {
            ResguardoPdvIncidencia::TIPO_FOLIO_NO_ENCONTRADO => PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FOLIO,
            ResguardoPdvIncidencia::TIPO_DANO => PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_DANO,
            ResguardoPdvIncidencia::TIPO_FALTANTE => PuntoVentaModulo::PERMISO_RESGUARDOS_INCIDENCIA_FALTANTE,
            default => PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
        };

        $permisos = [
            PuntoVentaModulo::PERMISO_RESGUARDOS_VER,
            $permisoEspecifico,
        ];

        if (in_array($tipoIncidencia, [
            ResguardoPdvIncidencia::TIPO_DANO,
            ResguardoPdvIncidencia::TIPO_FALTANTE,
        ], true)) {
            $permisos[] = PuntoVentaModulo::PERMISO_RESGUARDOS_AUTORIZAR_ENTREGA_INCIDENCIA;
        }

        return array_values(array_unique($permisos));
    }

    private function folio(ResguardoPdv $resguardo): string
    {
        $folio = trim((string) ($resguardo->snapshot_folio ?? ''));

        return $folio !== '' ? $folio : 'RSG-'.$resguardo->id;
    }

    private function claveNotificacion(string $tipoAlerta, string $idempotencyKey): string
    {
        return "pdv:notify:{$tipoAlerta}:{$idempotencyKey}";
    }
}
