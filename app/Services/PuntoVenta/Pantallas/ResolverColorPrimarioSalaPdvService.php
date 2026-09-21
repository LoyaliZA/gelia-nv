<?php

namespace App\Services\PuntoVenta\Pantallas;

use App\Models\PersonalizacionTema;
use Illuminate\Support\Facades\Schema;

final class ResolverColorPrimarioSalaPdvService
{
    private const PALETA = [
        'rosa' => '#ec4899',
        'azul' => '#3b82f6',
        'verde' => '#10b981',
        'amarillo' => '#f59e0b',
    ];

    public function hex(): string
    {
        $tema = $this->temaActivo();
        $config = is_array($tema?->configuracion) ? $tema->configuracion : [];

        $hex = strtolower(trim((string) ($config['color_hex'] ?? '')));
        if (preg_match('/^#[0-9a-f]{6}$/', $hex) === 1) {
            return $hex;
        }

        $nombre = strtolower(trim((string) ($config['color_nombre'] ?? '')));
        if (isset(self::PALETA[$nombre])) {
            return self::PALETA[$nombre];
        }

        return self::PALETA['rosa'];
    }

    private function temaActivo(): ?PersonalizacionTema
    {
        if (! Schema::hasTable('personalizacion_temas')) {
            return null;
        }

        $tema = PersonalizacionTema::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->first();

        return $tema instanceof PersonalizacionTema ? $tema : null;
    }
}
