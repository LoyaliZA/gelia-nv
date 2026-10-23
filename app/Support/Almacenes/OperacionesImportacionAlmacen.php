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
            self::FICHA_PRODUCTO => 'Nombre, marca, categoría, código de barras y demás datos del catálogo. No modifica existencias ni costos.',
            self::ASIGNACION_ALMACEN => 'Vincula el folio al almacén elegido y puede guardar la ubicación de asignación. No modifica existencias ni costos.',
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
                'frase' => 'El archivo identifica el producto por folio. Puede crear un folio nuevo o actualizar la ficha y la ubicación de asignación en el almacén elegido. Conserva costos, precios y existencias. Una celda vacía no borra el dato guardado.',
                'columnas' => 'Folio, SKU, descripción, marca, categoría, código de barras, peso, activo y ubicación',
                'operaciones' => [self::FICHA_PRODUCTO, self::ASIGNACION_ALMACEN],
                'plantilla' => 'productos',
            ],
            'costos' => [
                'titulo' => 'Costos y precios',
                'frase' => 'El archivo identifica el producto por folio y el folio tiene que existir. Puede modificar el costo, el costo de reposición y el precio de referencia de ese almacén. Conserva la ficha, la asignación y la existencia.',
                'columnas' => 'Folio, costo, costo de reposición y precio de referencia',
                'operaciones' => [self::COSTOS_PRECIOS],
                'plantilla' => 'costos',
            ],
            'existencias' => [
                'titulo' => 'Existencias',
                'frase' => 'El archivo identifica el producto por folio y el folio tiene que existir. Solo puede modificar la existencia de referencia de ese almacén. Conserva la ficha, la asignación, los costos, los precios y el resto del inventario.',
                'columnas' => 'Folio y existencia',
                'operaciones' => [self::CANTIDADES_REFERENCIA],
                'plantilla' => 'existencias',
            ],
            'completa' => [
                'titulo' => 'Carga completa',
                'frase' => 'El archivo identifica el producto por folio. Puede incluir ficha, asignación, costos, precios y existencias. Conserva el dato guardado cuando la celda viene vacía.',
                'columnas' => 'Folio, SKU, descripción, marca, categoría, código de barras, peso, activo, ubicación, existencia, costo, costo de reposición y precio de referencia',
                'operaciones' => self::todas(),
                'plantilla' => 'completa',
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
            'productos', 'asignacion' => 'productos',
            'costos' => 'costos',
            'existencias' => 'existencias',
            'inventario', 'completa' => 'completa',
            default => null,
        };

        if ($caso === null) {
            return [];
        }

        return self::casos()[$caso];
    }
}
