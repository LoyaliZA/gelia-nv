<?php

namespace App\Support\Almacenes;

use App\Models\User;

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
            if (in_array($op, [self::ASIGNACION_ALMACEN, self::CANTIDADES_REFERENCIA], true)) {
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

    public static function usuarioPuedeOperacion(User $user, string $operacion): bool
    {
        if ($user->can('catalogos.gestionar')) {
            return true;
        }

        $permiso = self::permisoParaOperacion($operacion);
        if ($permiso && $user->can($permiso)) {
            return true;
        }

        return $operacion === self::FICHA_PRODUCTO
            && ($user->can('gestion_interna.productos.gestionar') || $user->can('almacenes.productos.gestionar'));
    }

    public static function usuarioPuedeCaso(User $user, string $caso): bool
    {
        $definicion = self::definicionCasos()[$caso] ?? null;
        if ($definicion === null) {
            return false;
        }

        foreach ($definicion['operaciones'] as $operacion) {
            if (! self::usuarioPuedeOperacion($user, $operacion)) {
                return false;
            }
        }

        return true;
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
            self::FICHA_PRODUCTO => 'Nombre, marca, categoría, margen, medidas y demás datos del catálogo. No asigna almacén ni modifica existencias ni costos.',
            self::ASIGNACION_ALMACEN => 'Vincula el folio al almacén elegido y puede guardar la ubicación de asignación. No modifica existencias ni costos.',
            self::COSTOS_PRECIOS => 'Costo promedio y costo de reposición del producto, uno para toda la empresa. No modifica existencias ni el margen. El precio de Público General se recalcula.',
            self::CANTIDADES_REFERENCIA => 'Existencia y ubicación en el almacén, y la asignación si aún no existe. No crea el producto ni cambia costos.',
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
     * @return array<string, list<string>>
     */
    public static function casos(): array
    {
        $definiciones = [];
        foreach (self::definicionCasos() as $id => $caso) {
            $definiciones[$id] = $caso['operaciones'];
        }

        return $definiciones;
    }

    /**
     * @return array<string, array{titulo: string, frase: string, columnas: string, operaciones: list<string>, plantilla: string}>
     */
    public static function definicionCasos(): array
    {
        return [
            'productos' => [
                'titulo' => 'Productos',
                'frase' => 'El archivo identifica el producto por folio. Puede crear un folio nuevo o actualizar la ficha del catálogo, incluido el margen y las medidas. No asigna sucursal ni almacén. Conserva costos y existencias. Una celda vacía no borra el dato guardado. El precio de Público General se calcula y no se importa.',
                'columnas' => 'Folio, SKU, descripción, marca, categoría, código de barras, peso, margen, largo, ancho, altura y activo',
                'operaciones' => [self::FICHA_PRODUCTO],
                'plantilla' => 'productos',
            ],
            'costos' => [
                'titulo' => 'Costos',
                'frase' => 'El archivo identifica el producto por folio o SKU y el producto tiene que existir. Asigna el costo promedio y el costo de reposición de la empresa. No pide almacén, no modifica existencias y no captura el precio: el precio de Público General se recalcula con el margen.',
                'columnas' => 'Folio, costo promedio y costo de reposición',
                'operaciones' => [self::COSTOS_PRECIOS],
                'plantilla' => 'costos',
            ],
            'existencias' => [
                'titulo' => 'Existencias',
                'frase' => 'El archivo identifica el producto por folio o SKU y el producto tiene que existir. Modifica la existencia y la ubicación de ese almacén, y crea la asignación si falta. No crea el producto ni escribe costos.',
                'columnas' => 'Folio, ubicación y existencia',
                'operaciones' => [self::CANTIDADES_REFERENCIA, self::ASIGNACION_ALMACEN],
                'plantilla' => 'existencias',
            ],
        ];
    }

    /**
     * @param  list<string>  $operaciones
     */
    public static function casoDesdeOperaciones(array $operaciones): ?string
    {
        $firma = self::firma($operaciones);
        foreach (self::casos() as $caso => $ops) {
            if (self::firma($ops) === $firma) {
                return $caso;
            }
        }

        return null;
    }

    public static function tituloCaso(string $caso): string
    {
        return self::definicionCasos()[$caso]['titulo'] ?? $caso;
    }

    /**
     * @param  list<string>  $operaciones
     */
    public static function firma(array $operaciones): string
    {
        $operaciones = array_values(array_unique($operaciones));
        sort($operaciones);

        return implode(',', $operaciones);
    }

    /**
     * @return list<string>
     */
    public static function operacionesDesdePreset(string $preset): array
    {
        $caso = match ($preset) {
            'productos' => 'productos',
            'asignacion' => 'existencias',
            'costos' => 'costos',
            'existencias', 'inventario', 'completa' => 'existencias',
            default => null,
        };

        if ($caso === null) {
            return [];
        }

        return self::casos()[$caso];
    }
}
