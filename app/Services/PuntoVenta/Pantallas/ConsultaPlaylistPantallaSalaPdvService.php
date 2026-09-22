<?php

namespace App\Services\PuntoVenta\Pantallas;

use App\Models\Medios\Medio;
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
            ->with('medio')
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
            ->filter(fn (PdvPantallaPublicidad $item): bool => $this->reproducible($item))
            ->map(fn (PdvPantallaPublicidad $item): array => $this->serializarPublico($item))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function administrar(int $sucursalId, ?CarbonInterface $ahora = null): array
    {
        $ahora ??= now();

        return PdvPantallaPublicidad::query()
            ->with('medio')
            ->where(function ($query) use ($sucursalId): void {
                $query->whereNull('sucursal_id')->orWhere('sucursal_id', $sucursalId);
            })
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (PdvPantallaPublicidad $item): array => $this->serializarAdmin($item, $ahora))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializarAdmin(PdvPantallaPublicidad $item, ?CarbonInterface $ahora = null): array
    {
        $ahora ??= now();
        $medio = $item->medio;

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
            'vigente_desde' => $item->vigente_desde?->timezone(config('app.timezone'))->format('Y-m-d\\TH:i'),
            'vigente_hasta' => $item->vigente_hasta?->timezone(config('app.timezone'))->format('Y-m-d\\TH:i'),
            'nombre_original' => $item->nombre_original ?: $medio?->nombre_original,
            'tamano_bytes' => $medio?->tamano_bytes,
            'mime_type' => $medio?->mime_type,
            'estado' => $item->estadoCalculado($ahora),
            'medio_id' => $item->medio_id,
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

    private function reproducible(PdvPantallaPublicidad $item): bool
    {
        if ($item->urlPublica() === null) {
            return false;
        }
        $medio = $item->medio;
        if ($medio instanceof Medio && $medio->estado !== Medio::ESTADO_READY) {
            return false;
        }

        return true;
    }
}
