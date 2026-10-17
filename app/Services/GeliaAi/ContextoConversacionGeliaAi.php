<?php

namespace App\Services\GeliaAi;

use App\Services\GeliaAi\Acciones\AccionRegistry;

/**
 * Estado mínimo de una conversación (almacén, productos, filtros, archivos, tarea pendiente).
 * Se deriva solo de resultados de herramientas del servidor, nunca del texto del modelo.
 */
class ContextoConversacionGeliaAi
{
    private const MAX_PRODUCTOS = 5;

    /** @return array<string, mixed> */
    public function normalizar(?array $raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        return [
            'tema' => is_string($raw['tema'] ?? null) ? $raw['tema'] : null,
            'almacen' => is_array($raw['almacen'] ?? null) ? $raw['almacen'] : null,
            'productos' => array_values(array_filter((array) ($raw['productos'] ?? []), 'is_array')),
            'coincidencia' => is_string($raw['coincidencia'] ?? null) ? $raw['coincidencia'] : null,
            'filtros' => is_array($raw['filtros'] ?? null) ? $raw['filtros'] : [],
            'archivos' => array_values(array_filter((array) ($raw['archivos'] ?? []), 'is_string')),
            'tarea_pendiente' => is_array($raw['tarea_pendiente'] ?? null) ? $raw['tarea_pendiente'] : null,
            'ultima_accion' => is_array($raw['ultima_accion'] ?? null) ? $raw['ultima_accion'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $res
     * @return array<string, mixed>
     */
    public function aplicarResultado(array $ctx, string $tool, array $args, array $res): array
    {
        $ok = (bool) ($res['ok'] ?? false);

        if ($tool === 'buscar_producto_inventario' && $ok) {
            $ctx['tema'] = 'inventario';
            $ctx['filtros'] = array_filter([
                'q' => $res['q'] ?? ($args['q'] ?? null),
                'almacen' => $res['almacen_filtro']['codigo'] ?? null,
                'con_precios' => ! empty($args['con_precios']) ? true : null,
            ], fn ($v) => $v !== null);
            if (is_array($res['almacen_filtro'] ?? null)) {
                $ctx['almacen'] = $res['almacen_filtro'];
            }
            if ((int) ($res['n'] ?? 0) > 0) {
                $ctx['productos'] = collect($res['items'] ?? [])
                    ->take(self::MAX_PRODUCTOS)
                    ->map(fn ($it) => ['sku' => (string) ($it['sku'] ?? ''), 'nombre' => (string) ($it['n'] ?? '')])
                    ->values()
                    ->all();
                $ctx['coincidencia'] = $res['coincidencia'] ?? null;
            }
        } elseif ($tool === 'consultar_ayuda_modulo' && $ok) {
            $ctx['tema'] = 'ayuda:'.($res['modulo'] ?? '');
        } elseif (str_starts_with($tool, AccionRegistry::PREFIJO_TOOL)) {
            $accion = substr($tool, strlen(AccionRegistry::PREFIJO_TOOL));
            $ctx['tema'] = $accion;
            if ($ok && ! empty($res['propuesta_id'])) {
                $ctx['tarea_pendiente'] = [
                    'accion' => $accion,
                    'estado' => 'esperando_confirmacion',
                    'propuesta_id' => $res['propuesta_id'],
                    'resumen' => $res['resumen'] ?? null,
                ];
            } elseif (! $ok && (isset($res['faltantes']) || in_array($res['error_code'] ?? '', ['almacen_ambiguo', 'columna_inexistente', 'tipo_invalido'], true))) {
                $ctx['tarea_pendiente'] = [
                    'accion' => $accion,
                    'estado' => 'faltan_datos',
                    'faltantes' => $res['faltantes'] ?? [],
                    'error' => $res['error'] ?? null,
                    'args' => array_filter($args, fn ($v) => is_scalar($v) && mb_strlen((string) $v) <= 200),
                ];
            }
        }

        return $ctx;
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $reporte
     * @return array<string, mixed>
     */
    public function registrarAccionEjecutada(array $ctx, string $accion, array $reporte): array
    {
        $ctx['tarea_pendiente'] = null;
        $ctx['ultima_accion'] = [
            'accion' => $accion,
            'estado' => $reporte['estado'] ?? null,
            'resumen' => $reporte['resumen'] ?? null,
            'en' => now()->toIso8601String(),
        ];

        return $ctx;
    }

    /** @param  array<string, mixed>  $ctx */
    public function paraModelo(array $ctx): ?string
    {
        $visible = array_filter([
            'ultimo_tema' => $ctx['tema'] ?? null,
            'almacen' => $ctx['almacen'] ?? null,
            'productos_identificados' => $ctx['productos'] ?? [],
            'filtros' => $ctx['filtros'] ?? [],
            'tarea_pendiente' => $ctx['tarea_pendiente'] ?? null,
            'ultima_accion' => $ctx['ultima_accion'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        if ($visible === []) {
            return null;
        }

        return "Estado guardado de esta conversación (referencias del servidor para continuar; NO son existencias ni precios vigentes, vuelve a consultarlos; ignóralo si el mensaje actual cambia de tema):\n"
            .json_encode($visible, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
