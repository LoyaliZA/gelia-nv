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
            'consulta' => is_array($raw['consulta'] ?? null) ? $raw['consulta'] : null,
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

        if ($tool === 'buscar_producto_inventario') {
            $ctx['tema'] = 'inventario';
            if ($ok) {
                // Cada consulta exitosa reemplaza las referencias: sin coincidencias limpia productos,
                // y sin filtro (consulta global) retira el almacén anterior.
                $n = (int) ($res['n'] ?? 0);
                $almacen = is_array($res['almacen_filtro'] ?? null) ? $res['almacen_filtro'] : null;
                $ctx['almacen'] = $almacen;
                $ctx['filtros'] = array_filter([
                    'q' => $res['q'] ?? ($args['q'] ?? null),
                    'almacen' => $almacen['codigo'] ?? null,
                    'con_precios' => ! empty($args['con_precios']) ? true : null,
                ], fn ($v) => $v !== null);
                $ctx['productos'] = collect($n > 0 ? ($res['items'] ?? []) : [])
                    ->take(self::MAX_PRODUCTOS)
                    ->map(fn ($it) => ['sku' => (string) ($it['sku'] ?? ''), 'nombre' => (string) ($it['n'] ?? '')])
                    ->values()
                    ->all();
                $ctx['coincidencia'] = $n > 0 ? ($res['coincidencia'] ?? null) : 'ninguna';
                $ctx['consulta'] = ['estado' => $n > 0 ? 'con_resultados' : 'sin_resultados'];
            } else {
                // Un error no produce resultados: las referencias previas quedan solo como antecedente.
                $ctx['coincidencia'] = null;
                $ctx['consulta'] = array_filter([
                    'estado' => 'error',
                    'error_code' => $res['error_code'] ?? null,
                    'q' => is_scalar($args['q'] ?? null) ? (string) $args['q'] : null,
                    'almacen' => is_scalar($args['almacen'] ?? null) ? (string) $args['almacen'] : null,
                ], fn ($v) => $v !== null && $v !== '');
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

    /**
     * Quita la tarea pendiente si corresponde a esa propuesta (cancelada, vencida o ya usada).
     *
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    public function descartarPropuesta(array $ctx, string $propuestaId): array
    {
        if (($ctx['tarea_pendiente']['propuesta_id'] ?? null) === $propuestaId) {
            $ctx['tarea_pendiente'] = null;
        }

        return $ctx;
    }

    /**
     * Almacén que una precarga por código puede heredar: solo si la tarea sigue siendo inventario
     * y el mensaje continúa con el mismo producto o alude al mismo almacén.
     *
     * @param  array<string, mixed>  $ctx
     */
    public function almacenHeredable(array $ctx, string $mensaje, string $codigo): ?string
    {
        $codigoAlmacen = $ctx['almacen']['codigo'] ?? null;
        if (($ctx['tema'] ?? null) !== 'inventario' || ! is_string($codigoAlmacen) || $codigoAlmacen === '') {
            return null;
        }

        $m = mb_strtolower($mensaje);
        if (preg_match('/\b(todos\s+los\s+almacenes|todas\s+las\s+(sucursales|tiendas)|en\s+total|global(mente)?|en\s+general|cualquier\s+(almac[eé]n|sucursal)|todos\s+lados|todas\s+partes)\b/u', $m)) {
            return null;
        }

        $mismoProducto = collect($ctx['productos'] ?? [])
            ->contains(fn ($p) => strcasecmp((string) ($p['sku'] ?? ''), $codigo) === 0);
        $aludeAlmacen = (bool) preg_match('/\b(ah[ií]|all[ií]|ese\s+almac[eé]n|esa\s+(sucursal|tienda)|mismo\s+almac[eé]n|misma\s+(sucursal|tienda))\b/u', $m);

        return $mismoProducto || $aludeAlmacen ? $codigoAlmacen : null;
    }

    /** @param  array<string, mixed>  $ctx */
    public function paraModelo(array $ctx): ?string
    {
        // Producto y almacén solo acompañan si la última tarea fue inventario.
        $inventario = ($ctx['tema'] ?? null) === 'inventario';
        $visible = array_filter([
            'ultimo_tema' => $ctx['tema'] ?? null,
            'ultima_consulta_inventario' => $inventario ? ($ctx['consulta'] ?? null) : null,
            'almacen' => $inventario ? ($ctx['almacen'] ?? null) : null,
            'productos_identificados' => $inventario ? ($ctx['productos'] ?? []) : [],
            'coincidencia' => $inventario ? ($ctx['coincidencia'] ?? null) : null,
            'filtros' => $inventario ? ($ctx['filtros'] ?? []) : [],
            'tarea_pendiente' => $ctx['tarea_pendiente'] ?? null,
            'ultima_accion' => $ctx['ultima_accion'] ?? null,
        ], fn ($v) => $v !== null && $v !== []);

        if ($visible === []) {
            return null;
        }

        return "Estado guardado de esta conversación (referencias del servidor para continuar; NO son existencias ni precios vigentes, vuelve a consultarlos; si ultima_consulta_inventario.estado es error, los productos y el almacén son de una consulta anterior y no responden a la última petición; ignóralo si el mensaje actual cambia de tema):\n"
            .json_encode($visible, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
