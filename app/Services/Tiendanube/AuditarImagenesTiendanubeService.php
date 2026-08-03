<?php

namespace App\Services\Tiendanube;

use App\Models\Tiendanube\TiendanubeProductoImagen;
use Illuminate\Support\Facades\Http;
use Throwable;

class AuditarImagenesTiendanubeService
{
    /**
     * @return array{procesadas: int, actualizadas: int, fallidas: int}
     */
    public function ejecutar(int $limite = 100, bool $force = false): array
    {
        $query = TiendanubeProductoImagen::query()
            ->whereNotNull('src')
            ->where('src', '!=', '')
            ->orderBy('id');

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('width')->orWhereNull('height');
            });
        }

        $imagenes = $query->limit(max(1, $limite))->get();
        $procesadas = 0;
        $actualizadas = 0;
        $fallidas = 0;

        foreach ($imagenes as $imagen) {
            $procesadas++;
            try {
                $dims = $this->medirUrl((string) $imagen->src);
                if ($dims === null) {
                    $fallidas++;
                    continue;
                }

                [$width, $height] = $dims;
                $flags = OptimizarImagenTiendanubeService::flagsDesdeDimensiones($width, $height);
                $imagen->fill(array_merge([
                    'width' => $width,
                    'height' => $height,
                ], $flags));
                $imagen->save();
                $actualizadas++;
            } catch (Throwable) {
                $fallidas++;
            }
        }

        return compact('procesadas', 'actualizadas', 'fallidas');
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public function medirUrl(string $url): ?array
    {
        $response = Http::timeout(12)
            ->withHeaders(['Accept' => 'image/*,*/*'])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $info = @getimagesizefromstring($response->body());
        if (! is_array($info) || empty($info[0]) || empty($info[1])) {
            return null;
        }

        return [(int) $info[0], (int) $info[1]];
    }
}
