<?php

namespace App\Services\Tiendanube;

use App\Exceptions\Tiendanube\TiendanubeOperacionConflictException;
use App\Models\Tiendanube\TiendanubeOperacionTienda;
use Illuminate\Support\Str;

class TiendanubeOperacionTiendaService
{
    public const TIPO_CATALOGO_SYNC = 'catalogo_sync';

    public const TIPO_CATALOGO_WIPE = 'catalogo_wipe';

    public const TIPO_PRODUCTO_WRITE = 'producto_write';

    public const TIPO_IMAGE_IMPORT = 'image_import';

    public const TIPO_CAMBIO_TIENDA = 'cambio_tienda';

    /**
     * Jerarquía: catalogo_sync y catalogo_wipe son exclusivos por tienda.
     * Escrituras, importaciones y cambio de tienda se rechazan mientras hay sync/wipe activo.
     */
    public function asegurarFila(int $storeId): TiendanubeOperacionTienda
    {
        return TiendanubeOperacionTienda::query()->firstOrCreate(
            ['store_id' => $storeId],
            ['estado' => TiendanubeOperacionTienda::ESTADO_LIBERADA]
        );
    }

    public function adquirirExclusiva(int $storeId, string $tipo, int $ownerId, int $generation): string
    {
        $this->asegurarFila($storeId);
        $this->expirarSiVencida($storeId);

        $now = now();
        $token = (string) Str::uuid();
        $leaseSeconds = max(30, (int) config('tiendanube.sync_lease_seconds', 120));

        $affected = TiendanubeOperacionTienda::query()
            ->where('store_id', $storeId)
            ->where(function ($query) use ($now) {
                $query->where('estado', '!=', TiendanubeOperacionTienda::ESTADO_ACTIVA)
                    ->orWhereNull('lease_expires_at')
                    ->orWhere('lease_expires_at', '<', $now);
            })
            ->update([
                'tipo' => $tipo,
                'owner_id' => $ownerId,
                'config_generation' => $generation,
                'lease_token' => $token,
                'lease_expires_at' => $now->copy()->addSeconds($leaseSeconds),
                'estado' => TiendanubeOperacionTienda::ESTADO_ACTIVA,
                'updated_at' => $now,
            ]);

        if ($affected !== 1) {
            throw new TiendanubeOperacionConflictException(
                'Ya hay una operación de tienda en curso. Reintente cuando termine.'
            );
        }

        return $token;
    }

    public function renovar(int $storeId, string $token): bool
    {
        $now = now();
        $leaseSeconds = max(30, (int) config('tiendanube.sync_lease_seconds', 120));

        $affected = TiendanubeOperacionTienda::query()
            ->where('store_id', $storeId)
            ->where('lease_token', $token)
            ->where('estado', TiendanubeOperacionTienda::ESTADO_ACTIVA)
            ->update([
                'lease_expires_at' => $now->copy()->addSeconds($leaseSeconds),
                'updated_at' => $now,
            ]);

        return $affected === 1;
    }

    public function liberar(int $storeId, ?string $token = null, ?int $ownerId = null): void
    {
        $query = TiendanubeOperacionTienda::query()
            ->where('store_id', $storeId)
            ->where('estado', TiendanubeOperacionTienda::ESTADO_ACTIVA);

        if ($token !== null) {
            $query->where('lease_token', $token);
        } elseif ($ownerId !== null) {
            $query->where('owner_id', $ownerId);
        }

        $query->update([
            'estado' => TiendanubeOperacionTienda::ESTADO_LIBERADA,
            'tipo' => null,
            'owner_id' => null,
            'lease_token' => null,
            'lease_expires_at' => null,
            'updated_at' => now(),
        ]);
    }

    public function hayCatalogoSyncActivo(?int $storeId = null): bool
    {
        $this->expirarVencidas();

        $query = TiendanubeOperacionTienda::query()
            ->where('estado', TiendanubeOperacionTienda::ESTADO_ACTIVA)
            ->where('tipo', self::TIPO_CATALOGO_SYNC)
            ->where('lease_expires_at', '>=', now());

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->exists();
    }

    public function hayExclusivaActiva(?int $storeId = null): bool
    {
        $this->expirarVencidas();

        $query = TiendanubeOperacionTienda::query()
            ->where('estado', TiendanubeOperacionTienda::ESTADO_ACTIVA)
            ->whereIn('tipo', [self::TIPO_CATALOGO_SYNC, self::TIPO_CATALOGO_WIPE])
            ->where('lease_expires_at', '>=', now());

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $query->exists();
    }

    public function assertAdmisible(string $tipo, ?int $storeId = null): void
    {
        if ($tipo === self::TIPO_CATALOGO_SYNC) {
            if ($this->hayExclusivaActiva($storeId)) {
                throw new TiendanubeOperacionConflictException('Ya hay una sincronización u operación exclusiva en curso.');
            }

            return;
        }

        if ($this->hayExclusivaActiva($storeId)) {
            throw new TiendanubeOperacionConflictException(
                'No se puede continuar: hay una sincronización o limpieza de catálogo en curso.'
            );
        }
    }

    public function tokenActivo(int $storeId): ?string
    {
        $op = TiendanubeOperacionTienda::query()
            ->where('store_id', $storeId)
            ->where('estado', TiendanubeOperacionTienda::ESTADO_ACTIVA)
            ->where('lease_expires_at', '>=', now())
            ->first();

        return $op?->lease_token;
    }

    private function expirarSiVencida(int $storeId): void
    {
        TiendanubeOperacionTienda::query()
            ->where('store_id', $storeId)
            ->where('estado', TiendanubeOperacionTienda::ESTADO_ACTIVA)
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<', now())
            ->update([
                'estado' => TiendanubeOperacionTienda::ESTADO_EXPIRADA,
                'tipo' => null,
                'owner_id' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function expirarVencidas(): void
    {
        TiendanubeOperacionTienda::query()
            ->where('estado', TiendanubeOperacionTienda::ESTADO_ACTIVA)
            ->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<', now())
            ->update([
                'estado' => TiendanubeOperacionTienda::ESTADO_EXPIRADA,
                'tipo' => null,
                'owner_id' => null,
                'lease_token' => null,
                'lease_expires_at' => null,
                'updated_at' => now(),
            ]);
    }
}
