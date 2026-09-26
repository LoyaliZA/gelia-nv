<?php

namespace App\Support\Almacenes;

final class OperacionesImportacionAlmacen
{
    public const FICHA_PRODUCTO = 'ficha_producto';

    public const ASIGNACION_ALMACEN = 'asignacion_almacen';

    public const COSTOS_PRECIOS = 'costos_precios';

    public const CANTIDADES_REFERENCIA = 'cantidades_referencia';

    /**
     * @return list<string>
     */
    public static function todas(): array
    {
        return [
            self::FICHA_PRODUCTO,
            self::ASIGNACION_ALMACEN,
            self::COSTOS_PRECIOS,
            self::CANTIDADES_REFERENCIA,
        ];
    }

    /**
     * @param  list<string>  $operaciones
     */
    public static function requiereAlmacen(array $operaciones): bool
    {
        foreach ($operaciones as $op) {
            if (in_array($op, [self::ASIGNACION_ALMACEN, self::COSTOS_PRECIOS, self::CANTIDADES_REFERENCIA], true)) {
                return true;
            }
        }

        return false;
    }

    public static function permisoParaOperacion(string $operacion): ?string
    {
        return match ($operacion) {
            self::FICHA_PRODUCTO => 'gestion_interna.productos.importar',
            self::ASIGNACION_ALMACEN => 'almacenes.inventarios.importar',
            self::CANTIDADES_REFERENCIA => 'almacenes.inventarios.importar',
            self::COSTOS_PRECIOS => 'almacenes.costos.importar',
            default => null,
        };
    }

    public static function etiqueta(string $operacion): string
    {
        return match ($operacion) {
            self::FICHA_PRODUCTO => 'Actualizar productos',
            self::ASIGNACION_ALMACEN => 'Vincular productos al almacén',
            self::COSTOS_PRECIOS => 'Actualizar costos y precios',
            self::CANTIDADES_REFERENCIA => 'Actualizar existencias',
            default => $operacion,
        };
    }

    public static function descripcionCorta(string $operacion): string
    {
        return match ($operacion) {
            self::FICHA_PRODUCTO => 'Nombre, marca, categoría, código de barras y demás datos del catálogo. No modifica existencias ni costos.',
            self::ASIGNACION_ALMACEN => 'Relaciona cada SKU con el almacén elegido (ubicación opcional). No modifica existencias ni costos.',
            self::COSTOS_PRECIOS => 'Costo, costo de reposición y precio de referencia del almacén. No modifica existencias ni datos del producto.',
            self::CANTIDADES_REFERENCIA => 'Existencias manuales de referencia en el almacén. No activa el producto ni cambia costos por sí sola.',
            default => '',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function etiquetasParaUi(): array
    {
        $out = [];
        foreach (self::todas() as $op) {
            $out[$op] = self::etiqueta($op);
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function operacionesDesdePreset(string $preset): array
    {
        return match ($preset) {
            'productos' => [self::FICHA_PRODUCTO],
            'costos' => [self::COSTOS_PRECIOS],
            'inventario' => [self::FICHA_PRODUCTO, self::CANTIDADES_REFERENCIA, self::COSTOS_PRECIOS],
            'existencias' => [self::CANTIDADES_REFERENCIA],
            'asignacion' => [self::ASIGNACION_ALMACEN],
            default => [],
        };
    }
}
