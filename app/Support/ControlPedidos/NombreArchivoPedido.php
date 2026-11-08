<?php

namespace App\Support\ControlPedidos;

class NombreArchivoPedido
{
    public static function mostrar(?string $nombre, string $tipo, int|string|null $pedido, int|string|null $id): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre ?? ''));
        $base = pathinfo($nombre, PATHINFO_FILENAME);
        $tecnico = $base === '' || mb_strlen($nombre) > 64
            || preg_match('/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}|[a-z0-9_-]{28,}/i', $base);
        if (! $tecnico) {
            return $nombre;
        }
        $etiqueta = match ($tipo) {
            'pdf_pedido', 'cotizacion' => 'pedido',
            'anexo_piezas' => 'anexo',
            'remision' => 'remision',
            'comprobante' => 'pago',
            'identificacion' => 'identificacion',
            default => 'evidencia',
        };
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

        return implode('-', array_filter([$etiqueta, $pedido, $id], fn ($v) => $v !== null && $v !== '')).($extension ? '.'.$extension : '');
    }
}
