<?php

namespace App\Support\Manuales;

/**
 * Catálogo estático de manuales (sin CMS).
 */
class ManualesCatalog
{
    public const SLUG_CONTROL_PEDIDOS = 'control-pedidos';

    /**
     * @return list<array{
     *     slug: string,
     *     modulo: string,
     *     titulo: string,
     *     descripcion: string,
     *     permisosAny: list<string>,
     *     secciones: list<array{id: string, cargo: string, titulo: string, permisosAny: list<string>}>
     * }>
     */
    public static function todos(): array
    {
        return [
            [
                'slug' => self::SLUG_CONTROL_PEDIDOS,
                'modulo' => 'Logística · Gestión de pedidos',
                'titulo' => 'Gestión de pedidos',
                'descripcion' => 'Flujo operativo BMA: registrar, auditar, CEDIS, guías y direcciones. Estados, errores y ejemplos por cargo.',
                'permisosAny' => [
                    'control_pedidos.ver_listado',
                    'control_pedidos.crear',
                    'control_pedidos.auditar',
                    'control_pedidos.cedis',
                    'control_pedidos.delegado',
                    'control_pedidos.configurar_plazos',
                    'clientes.direcciones.ver',
                ],
                'secciones' => [
                    [
                        'id' => 'vendedora',
                        'cargo' => 'Vendedora',
                        'titulo' => 'Registrar pedidos',
                        'permisosAny' => ['control_pedidos.ver_listado', 'control_pedidos.crear'],
                    ],
                    [
                        'id' => 'auxiliar',
                        'cargo' => 'Auxiliar',
                        'titulo' => 'Auditar pedidos',
                        'permisosAny' => ['control_pedidos.auditar'],
                    ],
                    [
                        'id' => 'cedis',
                        'cargo' => 'CEDIS',
                        'titulo' => 'Control de empaque y envío',
                        'permisosAny' => ['control_pedidos.cedis'],
                    ],
                    [
                        'id' => 'guias',
                        'cargo' => 'Guías',
                        'titulo' => 'Actualizar guías',
                        'permisosAny' => ['control_pedidos.delegado'],
                    ],
                    [
                        'id' => 'direcciones',
                        'cargo' => 'Direcciones',
                        'titulo' => 'Direcciones de envío',
                        'permisosAny' => ['clientes.direcciones.ver'],
                    ],
                ],
            ],
        ];
    }

    public static function porSlug(string $slug): ?array
    {
        foreach (self::todos() as $manual) {
            if ($manual['slug'] === $slug) {
                return $manual;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function todosLosPermisosHub(): array
    {
        $out = [];
        foreach (self::todos() as $manual) {
            foreach ($manual['permisosAny'] as $p) {
                $out[$p] = true;
            }
        }

        return array_keys($out);
    }
}
