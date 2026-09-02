<?php

namespace App\Support\PuntoVenta\Resguardos;

final class ColumnasExportacionResguardoPdv
{
    /**
     * Contrato de columnas del CSV operativo de listado (bandejas).
     *
     * @return array<string, string> clave interna => encabezado CSV
     */
    public static function listado(): array
    {
        return [
            'id' => 'ID resguardo',
            'bandeja' => 'Bandeja',
            'folio' => 'Folio',
            'estado' => 'Estado',
            'sucursal' => 'Sucursal',
            'numero_cliente' => 'No. cliente',
            'cliente' => 'Cliente',
            'pedido_id' => 'ID pedido',
            'pedido_folio' => 'Folio pedido',
            'pedido_remision' => 'Remisión',
            'bultos_esperados' => 'Bultos esperados',
            'incidencias_abiertas' => 'Incidencias abiertas',
            'salida_cedis' => 'Salida CEDIS',
            'recepcion_fisica' => 'Recepción física',
            'clasificaciones' => 'Clasificaciones',
            'fecha_limite_custodia' => 'Límite custodia',
            'fecha_limite_rezago' => 'Límite rezago',
            'entrega_bloqueada' => 'Entrega bloqueada',
        ];
    }

    /**
     * Contrato de columnas del CSV de auditoría por resguardo.
     *
     * @return array<string, string>
     */
    public static function auditoria(): array
    {
        return [
            'resguardo_id' => 'ID resguardo',
            'resguardo_folio' => 'Folio resguardo',
            'ocurrido_at' => 'Fecha y hora',
            'tipo_evento' => 'Tipo',
            'categoria' => 'Categoría',
            'estado_anterior' => 'Estado anterior',
            'estado_nuevo' => 'Estado nuevo',
            'actor' => 'Actor',
            'bulto_folio' => 'Bulto',
            'detalle' => 'Detalle',
        ];
    }
}
