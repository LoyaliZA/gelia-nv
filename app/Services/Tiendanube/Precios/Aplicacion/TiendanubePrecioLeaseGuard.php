<?php

namespace App\Services\Tiendanube\Precios\Aplicacion;

use App\Models\Tiendanube\TiendanubePrecioEjecucionEvento;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use Illuminate\Support\Str;

/**
 * Cada escritura del ítem exige, en el mismo UPDATE, el token, el estado y la vigencia.
 * Soltar un token vencido que sigue siendo el almacenado no continúa el trabajo:
 * deja conciliación. Renovar no alarga un lease ya vencido.
 */
class TiendanubePrecioLeaseGuard
{
    public function segundos(): int
    {
        return max(30, (int) config('tiendanube.precio_ejecucion_lease_seconds', 90));
    }

    public function reclamar(TiendanubePrecioEjecucionItem $item): ?string
    {
        $token = (string) Str::uuid();
        $now = now();
        $affected = TiendanubePrecioEjecucionItem::query()
            ->where('id', $item->id)
            ->whereIn('estado', [
                TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            ])
            ->where(function ($q) use ($now) {
                $q->whereNull('siguiente_intento_at')->orWhere('siguiente_intento_at', '<=', $now);
            })
            ->update([
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
                'lease_token' => $token,
                'lease_expires_at' => $now->copy()->addSeconds($this->segundos()),
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            return null;
        }

        $item->refresh();

        return $token;
    }

    public function reclamarParaVerificar(TiendanubePrecioEjecucionItem $item): ?string
    {
        $token = (string) Str::uuid();
        $now = now();
        $affected = TiendanubePrecioEjecucionItem::query()
            ->where('id', $item->id)
            ->where(function ($q) use ($now) {
                $q->whereIn('estado', [
                    TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO,
                    TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
                ])->orWhere(function ($q) use ($now) {
                    $q->where('estado', TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO)
                        ->whereNotNull('lease_expires_at')
                        ->where('lease_expires_at', '<', $now);
                });
            })
            ->update([
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
                'lease_token' => $token,
                'lease_expires_at' => $now->copy()->addSeconds($this->segundos()),
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            return null;
        }

        $item->refresh();

        return $token;
    }

    public function renovar(TiendanubePrecioEjecucionItem $item, string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $now = now();
        $affected = TiendanubePrecioEjecucionItem::query()
            ->where('id', $item->id)
            ->where('lease_token', $token)
            ->where('estado', TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO)
            ->where('lease_expires_at', '>=', $now)
            ->update([
                'lease_expires_at' => $now->copy()->addSeconds($this->segundos()),
                'updated_at' => $now,
            ]);

        if ($affected === 1) {
            $item->refresh();

            return true;
        }

        return false;
    }

    /**
     * @param  list<string>  $estados
     * @param  array<string, mixed>  $cambios
     */
    public function actualizarVigente(TiendanubePrecioEjecucionItem $item, string $token, array $cambios, array $estados): bool
    {
        if ($token === '' || $estados === []) {
            return false;
        }

        $now = now();
        $cambios['updated_at'] = $cambios['updated_at'] ?? $now;
        $affected = TiendanubePrecioEjecucionItem::query()
            ->where('id', $item->id)
            ->where('lease_token', $token)
            ->whereIn('estado', $estados)
            ->where('lease_expires_at', '>', $now)
            ->update($cambios);

        if ($affected === 1) {
            $item->refresh();

            return true;
        }

        return false;
    }

    /**
     * El token sigue guardado y ya venció. No pisa un token nuevo.
     * El vencimiento no afirma que Tiendanube haya descartado el envío.
     *
     * @param  array<string, mixed>  $evidenciaExtra
     */
    public function registrarEfectoTrasPerderVigencia(
        TiendanubePrecioEjecucionItem $item,
        string $token,
        array $evidenciaExtra,
        string $mensaje,
    ): bool {
        if ($token === '') {
            return false;
        }

        $actual = TiendanubePrecioEjecucionItem::query()
            ->where('id', $item->id)
            ->where('lease_token', $token)
            ->where('estado', TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO)
            ->first();
        $previa = is_array($actual?->evidencia) ? $actual->evidencia : (is_array($item->evidencia) ? $item->evidencia : []);
        $now = now();
        $affected = TiendanubePrecioEjecucionItem::query()
            ->where('id', $item->id)
            ->where('lease_token', $token)
            ->where('estado', TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO)
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', $now)
            ->update([
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO,
                'error_codigo' => 'conciliacion_exigida',
                'error_mensaje' => $mensaje,
                'lease_token' => null,
                'lease_expires_at' => null,
                'evidencia' => array_merge($previa, $evidenciaExtra, [
                    'verificacion_pendiente' => true,
                    'efecto' => 'posible',
                    'fase' => 'escritura_enviada',
                    'escritura' => true,
                    'conciliacion' => 'exigida',
                    'mensaje' => $mensaje,
                ]),
                'updated_at' => $now,
            ]);

        $this->anotarConciliacion($item, $token, $mensaje, $evidenciaExtra);

        if ($affected === 1) {
            $item->refresh();

            return true;
        }

        return false;
    }

    public function hayConciliacionAbierta(TiendanubePrecioEjecucionItem $item): bool
    {
        if ($item->ejecucion_id === null) {
            return false;
        }

        return TiendanubePrecioEjecucionEvento::query()
            ->where('ejecucion_id', $item->ejecucion_id)
            ->where('item_id', $item->id)
            ->where('tipo', TiendanubePrecioEjecucionEvento::TIPO_CONCILIACION_EXIGIDA)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $evidenciaExtra
     */
    public function anotarConciliacion(
        TiendanubePrecioEjecucionItem $item,
        string $token,
        string $mensaje,
        array $evidenciaExtra = [],
    ): void {
        if ($item->ejecucion_id === null) {
            return;
        }

        TiendanubePrecioEjecucionEvento::query()->create([
            'ejecucion_id' => $item->ejecucion_id,
            'item_id' => $item->id,
            'tipo' => TiendanubePrecioEjecucionEvento::TIPO_CONCILIACION_EXIGIDA,
            'payload' => [
                'lease_token' => $token,
                'efecto' => 'posible',
                'conciliacion' => 'exigida',
                'mensaje' => $mensaje,
                'evidencia' => $evidenciaExtra,
            ],
            'created_at' => now(),
        ]);
    }
}
