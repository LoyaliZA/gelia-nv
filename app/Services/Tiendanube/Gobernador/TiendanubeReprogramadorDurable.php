<?php

namespace App\Services\Tiendanube\Gobernador;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioEjecucion;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use App\Services\Tiendanube\Precios\Aplicacion\TiendanubePrecioLeaseGuard;
use DateTimeInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Persiste cuándo y cómo continuar, y encola un solo trabajo demorado.
 * No enciende colas dedicadas ni el scheduler de recuperación, y no vence leases.
 */
class TiendanubeReprogramadorDurable implements TiendanubeReprogramador
{
    public function __construct(
        private readonly TiendanubeGobernador $gobernador,
        private readonly TiendanubeEnrutadorColas $enrutador,
        private readonly ?TiendanubePrecioLeaseGuard $leases = null,
    ) {}

    public function reprogramar(TiendanubeTurnoResultado $turno, ?string $leaseToken = null): void
    {
        if ($turno->operacionId === '') {
            return;
        }

        $como = $this->como($turno);
        $propio = $this->reflejarEnItem($turno, $como, $leaseToken);
        if ($leaseToken !== null && $leaseToken !== '' && ! $propio) {
            return;
        }
        $nueva = true;
        if (config('tiendanube.gobernador_activo', false)) {
            $storeId = $this->storeId();
            if ($storeId > 0) {
                $nueva = $this->gobernador->guardarContinuacion($storeId, $turno->operacionId, $this->marca($turno, $como));
            }
        }

        if (! $nueva && ! $propio) {
            return;
        }

        $esperaMs = $turno->reanudarDespuesAt - $this->gobernador->marcaTemporal();
        if ($esperaMs <= 0) {
            return;
        }

        $ejecucionId = $this->ejecucionDe($turno->operacionId);
        if ($ejecucionId === null) {
            return;
        }

        $this->programarEjecucion($ejecucionId, now()->addMilliseconds($esperaMs), $leaseToken);
    }

    public function programarEjecucion(string $ejecucionId, DateTimeInterface $cuando, ?string $leaseToken = null): void
    {
        if ($cuando <= now()) {
            return;
        }

        $this->despacharUnaVez($ejecucionId, $cuando, $leaseToken);
    }

    public function despacharUnaVez(string $ejecucionId, ?DateTimeInterface $cuando = null, ?string $leaseToken = null): bool
    {
        $ejecucion = TiendanubePrecioEjecucion::query()->find($ejecucionId);
        if ($ejecucion === null || ! $this->abierta($ejecucion)) {
            return false;
        }

        $inmediato = $cuando === null || $cuando <= now();
        $marca = $inmediato ? 'debido' : (string) $cuando->getTimestamp();
        if ((string) $ejecucion->continuacion_despachada_en === $marca) {
            return false;
        }

        $token = (string) Str::uuid();
        $ahora = now();
        // ponytail: el UPDATE condiciona marca y token. Dos transacciones solapadas
        // en MySQL quedan pendientes de la validación aislada; SQLite no la acredita.
        $reclamado = TiendanubePrecioEjecucion::query()
            ->where('id', $ejecucionId)
            ->whereIn('estado', [
                TiendanubePrecioEjecucion::ESTADO_PENDIENTE,
                TiendanubePrecioEjecucion::ESTADO_PROCESANDO,
            ])
            ->where(function ($q) use ($marca) {
                $q->whereNull('continuacion_despachada_en')
                    ->orWhere('continuacion_despachada_en', '!=', $marca);
            })
            ->where(function ($q) use ($ahora) {
                $q->whereNull('continuacion_despacho_token')
                    ->orWhere('continuacion_despacho_expira_at', '<', $ahora);
            })
            ->update([
                'continuacion_programada_en' => $marca,
                'continuacion_despacho_token' => $token,
                'continuacion_despacho_expira_at' => $ahora->copy()->addSeconds(30),
                'updated_at' => $ahora,
            ]);
        if ($reclamado !== 1) {
            return false;
        }

        if (! $inmediato) {
            if (config('tiendanube.gobernador_activo', false)) {
                $storeId = $this->storeId();
                if ($storeId > 0) {
                    $this->gobernador->reservarPrograma($storeId, $ejecucionId, $marca);
                }
            }
            $this->marcarProgramacionLocal($ejecucionId, $marca, $leaseToken);
        }

        $estado = TiendanubePrecioEjecucion::query()->whereKey($ejecucionId)->value('estado');
        if (! in_array($estado, [
            TiendanubePrecioEjecucion::ESTADO_PENDIENTE,
            TiendanubePrecioEjecucion::ESTADO_PROCESANDO,
        ], true)) {
            $this->soltarReclamo($ejecucionId, $token);

            return false;
        }

        try {
            $this->enrutador->despacharPrecio($ejecucionId, $inmediato ? null : $cuando);
        } catch (Throwable $e) {
            $this->soltarReclamo($ejecucionId, $token);

            throw $e;
        }

        TiendanubePrecioEjecucion::query()
            ->where('id', $ejecucionId)
            ->where('continuacion_despacho_token', $token)
            ->update([
                'continuacion_despachada_en' => $marca,
                'continuacion_despacho_token' => null,
                'continuacion_despacho_expira_at' => null,
                'updated_at' => now(),
            ]);

        return true;
    }

    private function abierta(TiendanubePrecioEjecucion $ejecucion): bool
    {
        return in_array($ejecucion->estado, [
            TiendanubePrecioEjecucion::ESTADO_PENDIENTE,
            TiendanubePrecioEjecucion::ESTADO_PROCESANDO,
        ], true);
    }

    private function soltarReclamo(string $ejecucionId, string $token): void
    {
        TiendanubePrecioEjecucion::query()
            ->where('id', $ejecucionId)
            ->where('continuacion_despacho_token', $token)
            ->update([
                'continuacion_despacho_token' => null,
                'continuacion_despacho_expira_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function como(TiendanubeTurnoResultado $turno): string
    {
        if (str_contains($turno->operacionId, ':verificar') || str_contains($turno->operacionId, ':reconciliar')) {
            return 'verificar';
        }
        if (in_array($turno->fase, ['escritura_enviada', 'efecto_remoto_posible'], true)) {
            return 'verificar';
        }

        return 'procesar';
    }

    private function marca(TiendanubeTurnoResultado $turno, string $como): string
    {
        return json_encode([
            'reanudar_despues_at' => $turno->reanudarDespuesAt,
            'clase' => $turno->clase,
            'fase' => $turno->fase,
            'motivo' => $turno->motivo,
            'como' => $como,
        ], JSON_THROW_ON_ERROR);
    }

    private function reflejarEnItem(TiendanubeTurnoResultado $turno, string $como, ?string $leaseToken): bool
    {
        if ($leaseToken === null || $leaseToken === '') {
            return false;
        }

        $item = $this->itemDe($turno->operacionId);
        if ($item === null) {
            return false;
        }

        $guard = $this->leases ?? new TiendanubePrecioLeaseGuard();
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $esperaMs = $turno->reanudarDespuesAt - $this->gobernador->marcaTemporal();
        $cambios = [
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'lease_token' => null,
            'lease_expires_at' => null,
            'evidencia' => array_merge($previa, [
                'categoria' => $turno->motivo,
                'continuacion' => [
                    'reanudar_despues_at' => $turno->reanudarDespuesAt,
                    'clase' => $turno->clase,
                    'fase' => $turno->fase,
                    'motivo' => $turno->motivo,
                    'como' => $como,
                ],
            ]),
        ];
        if ($esperaMs > 0) {
            $cambios['siguiente_intento_at'] = now()->addMilliseconds($esperaMs);
        }
        return $guard->actualizarVigente($item, $leaseToken, $cambios, [
            TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
        ]);
    }

    private function marcarProgramacionLocal(string $ejecucionId, string $marca, ?string $leaseToken): bool
    {
        $item = TiendanubePrecioEjecucionItem::query()
            ->where('ejecucion_id', $ejecucionId)
            ->whereIn('estado', [
                TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
                TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
                TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO,
            ])
            ->orderBy('siguiente_intento_at')
            ->first();
        if ($item === null) {
            return true;
        }
        $previa = is_array($item->evidencia) ? $item->evidencia : [];
        $programada = (string) ($previa['continuacion']['programada_en'] ?? '');
        if ($programada === $marca) {
            return false;
        }
        $continuacion = is_array($previa['continuacion'] ?? null) ? $previa['continuacion'] : [];
        $continuacion['programada_en'] = $marca;
        $evidencia = array_merge($previa, ['continuacion' => $continuacion]);
        if ($item->estado === TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO
            && is_string($item->lease_token)
            && $item->lease_token !== '') {
            if ($leaseToken !== $item->lease_token) {
                return false;
            }
            $guard = $this->leases ?? new TiendanubePrecioLeaseGuard();

            return $guard->actualizarVigente($item, (string) $leaseToken, [
                'evidencia' => $evidencia,
            ], [TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO]);
        }
        $item->update([
            'evidencia' => $evidencia,
        ]);

        return true;
    }

    private function itemDe(string $operacionId): ?TiendanubePrecioEjecucionItem
    {
        if (! preg_match('/^precio:(\d+)/', $operacionId, $coincidencia)) {
            return null;
        }

        return TiendanubePrecioEjecucionItem::query()->find((int) $coincidencia[1]);
    }

    private function ejecucionDe(string $operacionId): ?string
    {
        return $this->itemDe($operacionId)?->ejecucion_id;
    }

    private function storeId(): int
    {
        return (int) (TiendanubeConfiguracion::obtener()->store_id ?? 0);
    }
}
