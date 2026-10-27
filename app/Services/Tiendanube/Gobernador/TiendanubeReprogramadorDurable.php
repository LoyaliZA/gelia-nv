<?php

namespace App\Services\Tiendanube\Gobernador;

use App\Models\Tiendanube\TiendanubeConfiguracion;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use DateTimeInterface;

/**
 * Persiste cuándo y cómo continuar, y encola un solo trabajo demorado.
 * No enciende colas dedicadas, no programa el scheduler de E2d y no vence leases.
 */
class TiendanubeReprogramadorDurable implements TiendanubeReprogramador
{
    public function __construct(
        private readonly TiendanubeGobernador $gobernador,
        private readonly TiendanubeEnrutadorColas $enrutador,
    ) {}

    public function reprogramar(TiendanubeTurnoResultado $turno): void
    {
        if ($turno->operacionId === '') {
            return;
        }

        $como = $this->como($turno);
        $nueva = true;
        if (config('tiendanube.gobernador_activo', false)) {
            $storeId = $this->storeId();
            if ($storeId > 0) {
                $nueva = $this->gobernador->guardarContinuacion($storeId, $turno->operacionId, $this->marca($turno, $como));
            }
        }

        $this->reflejarEnItem($turno, $como);
        if (! $nueva) {
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

        $this->programarEjecucion($ejecucionId, now()->addMilliseconds($esperaMs));
    }

    public function programarEjecucion(string $ejecucionId, DateTimeInterface $cuando): void
    {
        if ($cuando <= now()) {
            return;
        }

        $marca = (string) $cuando->getTimestamp();
        if (config('tiendanube.gobernador_activo', false)) {
            $storeId = $this->storeId();
            if ($storeId > 0 && ! $this->gobernador->reservarPrograma($storeId, $ejecucionId, $marca)) {
                return;
            }
        } elseif (! $this->marcarProgramacionLocal($ejecucionId, $marca)) {
            return;
        }

        $this->enrutador->despacharPrecio($ejecucionId, $cuando);
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

    private function reflejarEnItem(TiendanubeTurnoResultado $turno, string $como): void
    {
        $item = $this->itemDe($turno->operacionId);
        if ($item === null) {
            return;
        }
        if (! in_array($item->estado, [
            TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
            TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO,
            TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO,
        ], true)) {
            return;
        }

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
        $item->update($cambios);
    }

    private function marcarProgramacionLocal(string $ejecucionId, string $marca): bool
    {
        $item = TiendanubePrecioEjecucionItem::query()
            ->where('ejecucion_id', $ejecucionId)
            ->whereIn('estado', [
                TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
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
        $item->update([
            'evidencia' => array_merge($previa, ['continuacion' => $continuacion]),
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
