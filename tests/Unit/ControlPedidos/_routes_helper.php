<?php

/**
 * Contenido agregado de rutas Control de Pedidos (módulo modularizado Fase 8a).
 */
function control_pedidos_routes_content(string $root): string
{
    $content = '';
    foreach (glob($root.'/routes/web/modules/control-pedidos/*.php') ?: [] as $file) {
        $content .= file_get_contents($file);
    }

    return $content;
}

/**
 * Rutas web relevantes para self-checks de Control de Pedidos (módulo + public.php para evidencias).
 */
function control_pedidos_routes_with_public(string $root): string
{
    return control_pedidos_routes_content($root)
        .file_get_contents($root.'/routes/web/public.php');
}

/**
 * Rutas web + gestión interna (p. ej. productos.buscar usado desde CEDIS).
 */
function control_pedidos_routes_with_gestion_interna(string $root): string
{
    return control_pedidos_routes_content($root)
        .file_get_contents($root.'/routes/web/modules/gestion-interna.php');
}
