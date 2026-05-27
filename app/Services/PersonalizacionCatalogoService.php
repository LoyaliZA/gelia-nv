<?php

namespace App\Services;

use App\Models\PersonalizacionFondo;
use App\Models\PersonalizacionTema;
use App\Models\PersonalizacionTono;
use Illuminate\Support\Facades\Schema;

class PersonalizacionCatalogoService
{
    public static function tablaDisponible(string $tabla): bool
    {
        return Schema::hasTable($tabla);
    }

    public static function tonosActivos(): array
    {
        if (!self::tablaDisponible('personalizacion_tonos')) {
            return self::tonosDesdeConfig();
        }

        $tonos = PersonalizacionTono::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        if ($tonos->isEmpty()) {
            return self::tonosDesdeConfig();
        }

        return $tonos->map(fn (PersonalizacionTono $tono) => [
            'id'     => $tono->slug,
            'nombre' => $tono->nombre,
            'path'   => '/assets/sounds/' . ltrim($tono->archivo, '/'),
        ])->values()->all();
    }

    public static function tonosAdmin(): array
    {
        if (!self::tablaDisponible('personalizacion_tonos')) {
            return [];
        }

        return PersonalizacionTono::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (PersonalizacionTono $tono) => [
                'id'      => $tono->id,
                'slug'    => $tono->slug,
                'nombre'  => $tono->nombre,
                'archivo' => $tono->archivo,
                'path'    => '/assets/sounds/' . ltrim($tono->archivo, '/'),
                'activo'  => $tono->activo,
                'orden'   => $tono->orden,
            ])
            ->values()
            ->all();
    }

    public static function tonoIdsValidos(): array
    {
        if (!self::tablaDisponible('personalizacion_tonos')) {
            return collect(config('alertas.tonos', []))->pluck('id')->all();
        }

        $ids = PersonalizacionTono::query()
            ->where('activo', true)
            ->pluck('slug')
            ->all();

        return $ids ?: collect(config('alertas.tonos', []))->pluck('id')->all();
    }

    public static function fondosActivos(): array
    {
        if (!self::tablaDisponible('personalizacion_fondos')) {
            return self::fondosFallback();
        }

        $fondos = PersonalizacionFondo::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        if ($fondos->isEmpty()) {
            return self::fondosFallback();
        }

        return $fondos->map(fn (PersonalizacionFondo $fondo) => self::formatearFondo($fondo))->values()->all();
    }

    public static function fondosAdmin(): array
    {
        if (!self::tablaDisponible('personalizacion_fondos')) {
            return [];
        }

        return PersonalizacionFondo::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (PersonalizacionFondo $fondo) => self::formatearFondo($fondo, true))
            ->values()
            ->all();
    }

    public static function temasActivos(): array
    {
        if (!self::tablaDisponible('personalizacion_temas')) {
            return self::temasFallback();
        }

        $temas = PersonalizacionTema::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        if ($temas->isEmpty()) {
            return self::temasFallback();
        }

        return $temas->map(fn (PersonalizacionTema $tema) => self::formatearTema($tema))->values()->all();
    }

    public static function temasAdmin(): array
    {
        if (!self::tablaDisponible('personalizacion_temas')) {
            return [];
        }

        return PersonalizacionTema::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (PersonalizacionTema $tema) => self::formatearTema($tema, true))
            ->values()
            ->all();
    }

    private static function formatearFondo(PersonalizacionFondo $fondo, bool $admin = false): array
    {
        $preview = $fondo->tipo === 'vector'
            ? "/assets/backgrounds/{$fondo->valor}_pc.svg"
            : $fondo->valor;

        $item = [
            'id'      => $fondo->slug,
            'slug'    => $fondo->slug,
            'nombre'  => $fondo->nombre,
            'tipo'    => $fondo->tipo,
            'valor'   => $fondo->valor,
            'preview' => $preview,
            'activo'  => $fondo->activo,
            'orden'   => $fondo->orden,
        ];

        if ($admin) {
            $item['id']   = $fondo->id;
            $item['slug'] = $fondo->slug;
        }

        return $item;
    }

    private static function formatearTema(PersonalizacionTema $tema, bool $admin = false): array
    {
        $config = $tema->configuracion ?? [];

        $preset = [
            'id'         => $tema->slug,
            'slug'       => $tema->slug,
            'name'       => $tema->nombre,
            'modo'       => $config['modo'] ?? 'dark',
            'colorNombre'=> $config['color_nombre'] ?? 'rosa',
            'colorHex'   => $config['color_hex'] ?? '#ec4899',
            'bg'         => $config['fondo_base'] ?? 'none',
            'font'       => $config['fuente_principal'] ?? 'inter',
            'escala'     => $config['escala_fuente'] ?? 1,
            'glass'      => (bool) ($config['efecto_cristal'] ?? true),
            'layout'     => $config['layout_sidebar'] ?? 'floating_left',
            'sound'      => (bool) ($config['sonido'] ?? true),
            'activo'     => $tema->activo,
            'orden'      => $tema->orden,
        ];

        if ($admin) {
            $preset['id'] = $tema->id;
            $preset['configuracion'] = $config;
        }

        return $preset;
    }

    private static function tonosDesdeConfig(): array
    {
        return collect(config('alertas.tonos', []))
            ->map(fn (array $tono) => [
                'id'     => $tono['id'],
                'nombre' => $tono['nombre'],
                'path'   => '/assets/sounds/' . $tono['archivo'],
            ])
            ->values()
            ->all();
    }

    private static function fondosFallback(): array
    {
        $slugs = ['blob', 'blobscene', 'circle', 'layered', 'peaks', 'polygon', 'square', 'stacked', 'steps', 'wave'];

        return collect($slugs)->map(fn (string $slug) => [
            'id'      => $slug,
            'slug'    => $slug,
            'nombre'  => ucfirst($slug),
            'tipo'    => 'vector',
            'valor'   => $slug,
            'preview' => "/assets/backgrounds/{$slug}_pc.svg",
        ])->values()->all();
    }

    private static function temasFallback(): array
    {
        return [
            ['id' => 'gelia-signature', 'slug' => 'gelia-signature', 'name' => 'Gelia Signature', 'modo' => 'dark', 'colorNombre' => 'rosa', 'colorHex' => '#ec4899', 'bg' => 'blob', 'font' => 'montserrat', 'escala' => 1, 'glass' => true, 'layout' => 'floating_left', 'sound' => true],
            ['id' => 'gelia-oasis', 'slug' => 'gelia-oasis', 'name' => 'GELIA Oasis', 'modo' => 'light', 'colorNombre' => 'verde', 'colorHex' => '#10b981', 'bg' => 'stacked', 'font' => 'poppins', 'escala' => 1, 'glass' => false, 'layout' => 'floating_right', 'sound' => true],
            ['id' => 'cybertech', 'slug' => 'cybertech', 'name' => 'CyberTech', 'modo' => 'dark', 'colorNombre' => 'azul', 'colorHex' => '#3b82f6', 'bg' => 'polygon', 'font' => 'mono', 'escala' => 1, 'glass' => false, 'layout' => 'fixed', 'sound' => true],
        ];
    }
}
