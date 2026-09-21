<?php

namespace App\Services\PuntoVenta\Pantallas;

use App\Models\PuntoVenta\PdvPantallaPublicidad;
use Carbon\CarbonInterface;

final class ConsultaPlaylistPantallaSalaPdvService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function paraSucursal(int $sucursalId, CarbonInterface $ahora): array
    {
        return PdvPantallaPublicidad::query()
            ->where('activa', true)
            ->where(function ($query) use ($sucursalId): void {
                $query->whereNull('sucursal_id')->orWhere('sucursal_id', $sucursalId);
            })
            ->where(function ($query) use ($ahora): void {
                $query->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $ahora);
            })
            ->where(function ($query) use ($ahora): void {
                $query->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $ahora);
            })
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (PdvPantallaPublicidad $item): array => $this->serializarPublico($item))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function administrar(int $sucursalId): array
    {
        return PdvPantallaPublicidad::query()
            ->where(function ($query) use ($sucursalId): void {
                $query->whereNull('sucursal_id')->orWhere('sucursal_id', $sucursalId);
            })
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (PdvPantallaPublicidad $item): array => $this->serializarAdmin($item))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializarAdmin(PdvPantallaPublicidad $item): array
    {
        return [
            'id' => $item->id,
            'sucursal_id' => $item->sucursal_id,
            'alcance' => $item->sucursal_id === null ? 'global' : 'sucursal',
            'tipo' => $item->tipo,
            'url' => $item->urlPublica(),
            'duracion_seg' => $item->duracion_seg,
            'ajuste' => $item->ajuste,
            'orden' => $item->orden,
            'activa' => $item->activa,
            'vigente_desde' => $item->vigente_desde?->toIso8601String(),
            'vigente_hasta' => $item->vigente_hasta?->toIso8601String(),
            'nombre_original' => $item->nombre_original,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarPublico(PdvPantallaPublicidad $item): array
    {
        return [
            'id' => $item->id,
            'tipo' => $item->tipo,
            'url' => $item->urlPublica(),
            'duracion_seg' => $item->duracion_seg,
            'ajuste' => $item->ajuste,
        ];
    }
}
