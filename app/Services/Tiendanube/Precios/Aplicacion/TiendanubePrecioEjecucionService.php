<?php

namespace App\Services\Tiendanube\Precios\Aplicacion;

use App\Exceptions\Tiendanube\TiendanubePrecioEjecucionException;
use App\Exceptions\Tiendanube\TiendanubePrecioLoteException;
use App\Jobs\Tiendanube\Precios\ProcesarPrecioEjecucionJob;
use App\Models\Tiendanube\TiendanubePrecioEjecucion;
use App\Models\Tiendanube\TiendanubePrecioEjecucionEvento;
use App\Models\Tiendanube\TiendanubePrecioEjecucionItem;
use App\Services\Tiendanube\Precios\Lotes\TiendanubePrecioLoteService;
use App\Services\Tiendanube\TiendanubeOperacionTiendaService;

class TiendanubePrecioEjecucionService
{
    public function __construct(
        private readonly TiendanubePrecioLoteService $lotes,
        private readonly TiendanubePrecioEjecucionItemProcessor $processor,
        private readonly TiendanubeOperacionTiendaService $operaciones,
    ) {}

    public function obtenerAutorizada(string $ejecucionId, int $storeId, int $userId): TiendanubePrecioEjecucion
    {
        $ejecucion = TiendanubePrecioEjecucion::query()->find($ejecucionId);
        if (! $ejecucion || (int) $ejecucion->store_id !== $storeId) {
            throw new TiendanubePrecioEjecucionException('No se encontró la ejecución.', 'no_encontrada', 404);
        }
        try {
            $this->lotes->obtenerAutorizado($ejecucion->lote_id, $storeId, $userId);
        } catch (TiendanubePrecioLoteException $e) {
            throw new TiendanubePrecioEjecucionException($e->getMessage(), $e->codigo, $e->httpStatus, $e->errores, $e);
        }

        return $ejecucion;
    }

    public function porLote(string $loteId, int $storeId, int $userId): ?TiendanubePrecioEjecucion
    {
        try {
            $lote = $this->lotes->obtenerAutorizado($loteId, $storeId, $userId);
        } catch (TiendanubePrecioLoteException $e) {
            throw new TiendanubePrecioEjecucionException($e->getMessage(), $e->codigo, $e->httpStatus, $e->errores, $e);
        }
        $revision = $lote->revisionActual();
        if (! $revision) {
            return null;
        }

        return TiendanubePrecioEjecucion::query()
            ->where('revision_id', $revision->id)
            ->where('canal', TiendanubePrecioEjecucion::CANAL_API)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(TiendanubePrecioEjecucion $ejecucion): array
    {
        $ejecucion->refresh();
        $this->refrescarContadores($ejecucion);
        $ejecucion->refresh();
        $contadores = $ejecucion->contadores();

        return [
            'id' => $ejecucion->id,
            'lote_id' => $ejecucion->lote_id,
            'revision_id' => $ejecucion->revision_id,
            'store_id' => (int) $ejecucion->store_id,
            'canal' => $ejecucion->canal,
            'estado' => $ejecucion->estado,
            'checksum' => $ejecucion->checksum_revision,
            'resumen_campos' => $ejecucion->resumen_campos,
            'error_codigo' => $ejecucion->error_codigo,
            'error_mensaje' => $ejecucion->error_mensaje,
            'started_at' => $ejecucion->started_at?->toIso8601String(),
            'completed_at' => $ejecucion->completed_at?->toIso8601String(),
            ...$contadores,
            'exito_global' => $ejecucion->estado === TiendanubePrecioEjecucion::ESTADO_COMPLETADA
                && (int) $ejecucion->conflictos === 0
                && (int) $ejecucion->fallidas === 0
                && (int) $ejecucion->por_verificar === 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function listarItems(string $ejecucionId, int $storeId, int $userId, array $filtros = []): array
    {
        $ejecucion = $this->obtenerAutorizada($ejecucionId, $storeId, $userId);
        $query = $ejecucion->items()->orderBy('id');
        $filtro = $filtros['filtro'] ?? 'problemas';
        if ($filtro === 'problemas') {
            $query->whereIn('estado', [
                TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO,
                TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO,
                TiendanubePrecioEjecucionItem::ESTADO_FALLIDO,
                TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            ]);
        } elseif ($filtro !== 'todas') {
            $query->where('estado', $filtro);
        }

        $page = max(1, (int) ($filtros['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($filtros['per_page'] ?? 25)));
        $paginado = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginado->items())->map(fn (TiendanubePrecioEjecucionItem $item) => $this->serializarItem($item))->all(),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'total' => $paginado->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelar(string $ejecucionId, int $storeId, int $userId): array
    {
        $ejecucion = $this->obtenerAutorizada($ejecucionId, $storeId, $userId);
        $ejecucion->items()
            ->whereIn('estado', [
                TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            ])
            ->update([
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_CANCELADO,
                'error_codigo' => 'detenido',
                'error_mensaje' => 'Pendiente detenido. Las confirmadas permanecen aplicadas.',
            ]);

        $ejecucion->eventos()->create([
            'tipo' => TiendanubePrecioEjecucionEvento::TIPO_CANCELADA,
            'actor_id' => $userId,
            'payload' => ['canal' => 'api'],
            'created_at' => now(),
        ]);

        $this->refrescarContadores($ejecucion);

        return $this->payload($ejecucion);
    }

    /**
     * @return array<string, mixed>
     */
    public function reintentarItem(string $ejecucionId, int $itemId, int $storeId, int $userId): array
    {
        $ejecucion = $this->obtenerAutorizada($ejecucionId, $storeId, $userId);
        if ($ejecucion->estado === TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA) {
            throw new TiendanubePrecioEjecucionException(
                'La ejecución está suspendida por credenciales. No se reenvían ítems.',
                'suspendida',
                409
            );
        }

        $item = $ejecucion->items()->where('id', $itemId)->first();
        if (! $item || ! $item->esRecuperable()) {
            throw new TiendanubePrecioEjecucionException('El ítem no admite reintento.', 'no_recuperable');
        }

        $item->update([
            'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            'siguiente_intento_at' => now(),
            'lease_token' => null,
            'lease_expires_at' => null,
        ]);
        $ejecucion->eventos()->create([
            'tipo' => TiendanubePrecioEjecucionEvento::TIPO_REINTENTO,
            'item_id' => $item->id,
            'actor_id' => $userId,
            'payload' => ['canal' => 'api', 'variante_id' => $item->variante_id],
            'created_at' => now(),
        ]);

        if ($ejecucion->estado === TiendanubePrecioEjecucion::ESTADO_CANCELADA) {
            $ejecucion->update(['estado' => TiendanubePrecioEjecucion::ESTADO_PROCESANDO, 'completed_at' => null]);
        }

        $this->refrescarContadores($ejecucion);
        ProcesarPrecioEjecucionJob::dispatch($ejecucion->id);

        return $this->payload($ejecucion);
    }

    /**
     * @return array<string, mixed>
     */
    public function verificarItem(string $ejecucionId, int $itemId, int $storeId, int $userId): array
    {
        $ejecucion = $this->obtenerAutorizada($ejecucionId, $storeId, $userId);
        $item = $ejecucion->items()->where('id', $itemId)->first();
        if (! $item) {
            throw new TiendanubePrecioEjecucionException('No se encontró el ítem.', 'no_encontrada', 404);
        }

        $this->processor->verificar($item);
        $this->refrescarContadores($ejecucion);

        return $this->payload($ejecucion);
    }

    public function procesarLote(string $ejecucionId): void
    {
        $ejecucion = TiendanubePrecioEjecucion::query()->find($ejecucionId);
        if (! $ejecucion) {
            return;
        }
        if (in_array($ejecucion->estado, [
            TiendanubePrecioEjecucion::ESTADO_CANCELADA,
            TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA,
        ], true)) {
            $this->refrescarContadores($ejecucion);

            return;
        }

        $ejecucion->update(['estado' => TiendanubePrecioEjecucion::ESTADO_PROCESANDO]);
        $limite = max(1, (int) config('tiendanube.precio_ejecucion_batch', 15));
        $items = $ejecucion->items()
            ->whereIn('estado', [
                TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            ])
            ->where(function ($q) {
                $q->whereNull('siguiente_intento_at')->orWhere('siguiente_intento_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limite)
            ->get();

        foreach ($items as $item) {
            $ejecucion->refresh();
            if ($ejecucion->estado === TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA) {
                break;
            }
            $this->processor->procesar($item);
        }

        $this->refrescarContadores($ejecucion);
        $ejecucion->refresh();

        $quedan = $ejecucion->items()
            ->whereIn('estado', [
                TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            ])
            ->exists();

        if ($quedan && $ejecucion->estado === TiendanubePrecioEjecucion::ESTADO_PROCESANDO) {
            ProcesarPrecioEjecucionJob::dispatch($ejecucion->id);
        }
    }

    public function recuperar(): int
    {
        $ahora = now();
        TiendanubePrecioEjecucionItem::query()
            ->where('estado', TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO)
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<', $ahora)
            ->update([
                'estado' => TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
                'lease_token' => null,
                'siguiente_intento_at' => $ahora,
            ]);

        $recuperadas = 0;
        $abiertas = TiendanubePrecioEjecucion::query()
            ->whereIn('estado', [
                TiendanubePrecioEjecucion::ESTADO_PENDIENTE,
                TiendanubePrecioEjecucion::ESTADO_PROCESANDO,
            ])
            ->get();

        foreach ($abiertas as $ejecucion) {
            $pendientes = $ejecucion->items()
                ->whereIn('estado', [
                    TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                    TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
                ])
                ->exists();
            if ($pendientes) {
                ProcesarPrecioEjecucionJob::dispatch($ejecucion->id);
                $recuperadas++;
            } else {
                $this->refrescarContadores($ejecucion);
            }
        }

        return $recuperadas;
    }

    public function refrescarContadores(TiendanubePrecioEjecucion $ejecucion): void
    {
        $items = $ejecucion->items()->get();
        $conteos = [
            'pendientes' => 0,
            'procesando' => 0,
            'confirmadas' => 0,
            'conflictos' => 0,
            'por_verificar' => 0,
            'fallidas' => 0,
            'canceladas' => 0,
        ];
        foreach ($items as $item) {
            match ($item->estado) {
                TiendanubePrecioEjecucionItem::ESTADO_PENDIENTE,
                TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE => $conteos['pendientes']++,
                TiendanubePrecioEjecucionItem::ESTADO_PROCESANDO => $conteos['procesando']++,
                TiendanubePrecioEjecucionItem::ESTADO_CONFIRMADO => $conteos['confirmadas']++,
                TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO => $conteos['conflictos']++,
                TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO => $conteos['por_verificar']++,
                TiendanubePrecioEjecucionItem::ESTADO_FALLIDO => $conteos['fallidas']++,
                TiendanubePrecioEjecucionItem::ESTADO_CANCELADO => $conteos['canceladas']++,
                default => null,
            };
        }

        $estado = $ejecucion->estado;
        $terminada = $conteos['pendientes'] === 0 && $conteos['procesando'] === 0 && $conteos['por_verificar'] === 0;
        if ($estado !== TiendanubePrecioEjecucion::ESTADO_SUSPENDIDA) {
            if ($conteos['canceladas'] > 0 && $conteos['pendientes'] === 0 && $conteos['procesando'] === 0) {
                $estado = TiendanubePrecioEjecucion::ESTADO_CANCELADA;
            } elseif (! $terminada) {
                $estado = $conteos['procesando'] > 0 || $conteos['pendientes'] > 0
                    ? TiendanubePrecioEjecucion::ESTADO_PROCESANDO
                    : TiendanubePrecioEjecucion::ESTADO_PROCESANDO;
            } elseif ($conteos['conflictos'] === 0 && $conteos['fallidas'] === 0 && $conteos['canceladas'] === 0) {
                $estado = TiendanubePrecioEjecucion::ESTADO_COMPLETADA;
            } elseif ($conteos['confirmadas'] === 0 && $conteos['conflictos'] === 0) {
                $estado = TiendanubePrecioEjecucion::ESTADO_FALLIDA;
            } else {
                $estado = TiendanubePrecioEjecucion::ESTADO_PARCIAL;
            }
        }

        $cerrada = in_array($estado, [
            TiendanubePrecioEjecucion::ESTADO_COMPLETADA,
            TiendanubePrecioEjecucion::ESTADO_PARCIAL,
            TiendanubePrecioEjecucion::ESTADO_CANCELADA,
            TiendanubePrecioEjecucion::ESTADO_FALLIDA,
        ], true);

        $ejecucion->update([
            ...$conteos,
            'estado' => $estado,
            'completed_at' => $cerrada ? ($ejecucion->completed_at ?? now()) : null,
        ]);

        if ($cerrada && $ejecucion->lease_token) {
            $this->operaciones->liberar((int) $ejecucion->store_id, (string) $ejecucion->lease_token);
            $ejecucion->update(['lease_token' => null, 'lease_expires_at' => null]);
        }

        if ($cerrada && $ejecucion->eventos()->where('tipo', TiendanubePrecioEjecucionEvento::TIPO_COMPLETADA)->doesntExist()) {
            $ejecucion->eventos()->create([
                'tipo' => TiendanubePrecioEjecucionEvento::TIPO_COMPLETADA,
                'actor_id' => $ejecucion->user_id,
                'payload' => ['canal' => 'api', 'estado' => $estado, ...$conteos],
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function serializarItem(TiendanubePrecioEjecucionItem $item): array
    {
        return [
            'id' => $item->id,
            'producto_id' => (int) $item->producto_id,
            'variante_id' => (int) $item->variante_id,
            'nombre' => $item->producto_nombre,
            'sku' => $item->variante_sku,
            'atributos' => $item->variante_atributos,
            'imagen_url' => $item->imagen_url,
            'estado' => $item->estado,
            'valor_aprobado' => $item->valor_aprobado,
            'valor_remoto' => $item->valor_remoto_previo ?? $item->valor_confirmado,
            'valor_confirmado' => $item->valor_confirmado,
            'explicacion' => $item->error_mensaje,
            'error_codigo' => $item->error_codigo,
            'acciones' => $this->accionesItem($item),
        ];
    }

    /**
     * @return list<string>
     */
    private function accionesItem(TiendanubePrecioEjecucionItem $item): array
    {
        return match ($item->estado) {
            TiendanubePrecioEjecucionItem::ESTADO_CONFLICTO => ['revisar_conflicto'],
            TiendanubePrecioEjecucionItem::ESTADO_RESULTADO_INCIERTO => ['verificar_resultado'],
            TiendanubePrecioEjecucionItem::ESTADO_REINTENTO_PENDIENTE,
            TiendanubePrecioEjecucionItem::ESTADO_FALLIDO => ['reintentar'],
            default => [],
        };
    }
}
