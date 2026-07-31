<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;

class CamposIncorrectosPedidoBma
{
    public const DUENO_VENDEDORA = 'vendedora';

    public const DUENO_AUXILIAR = 'auxiliar';

    public const DUENO_GUIAS = 'guias';

    /** Prioridad de atención: más temprano primero. */
    public const PRIORIDAD_DUENOS = [
        self::DUENO_VENDEDORA,
        self::DUENO_AUXILIAR,
        self::DUENO_GUIAS,
    ];

    public const CAMPOS_VENDEDORA = [
        'domicilio',
        'destinatario',
        'telefono',
        'paqueteria',
        'tipo_guia',
        'referencia',
        'codigo_postal',
        'ciudad_estado',
    ];

    public const CAMPOS_AUXILIAR = [
        'remision',
        'folio_remision',
    ];

    public const CAMPOS_GUIAS = [
        'numero_rastreo',
        'guia_pdf',
    ];

    public const ALLOWLIST = [
        ...self::CAMPOS_VENDEDORA,
        ...self::CAMPOS_AUXILIAR,
        ...self::CAMPOS_GUIAS,
    ];

    /** Campos de envío / guía que invalidan la guía capturada. */
    public const INVALIDAN_GUIA = [
        ...self::CAMPOS_VENDEDORA,
        ...self::CAMPOS_GUIAS,
    ];

    public const INVALIDAN_REMISION = [
        ...self::CAMPOS_AUXILIAR,
    ];

    public const ETIQUETAS = [
        'domicilio' => 'Domicilio / dirección',
        'destinatario' => 'Destinatario',
        'telefono' => 'Teléfono',
        'paqueteria' => 'Paquetería',
        'tipo_guia' => 'Tipo de guía',
        'referencia' => 'Referencias',
        'codigo_postal' => 'Código postal',
        'ciudad_estado' => 'Ciudad / estado',
        'remision' => 'Remisión PDF',
        'folio_remision' => 'Folio de remisión',
        'numero_rastreo' => 'Número de guía',
        'guia_pdf' => 'PDF de guía',
    ];

    public static function filtrar(array $campos): array
    {
        return array_values(array_unique(array_intersect($campos, self::ALLOWLIST)));
    }

    public static function invalidanGuia(array $campos): bool
    {
        return count(array_intersect($campos, self::INVALIDAN_GUIA)) > 0;
    }

    public static function invalidanRemision(array $campos): bool
    {
        return count(array_intersect($campos, self::INVALIDAN_REMISION)) > 0;
    }

    public static function duenoDe(string $campo): ?string
    {
        if (in_array($campo, self::CAMPOS_VENDEDORA, true)) {
            return self::DUENO_VENDEDORA;
        }
        if (in_array($campo, self::CAMPOS_AUXILIAR, true)) {
            return self::DUENO_AUXILIAR;
        }
        if (in_array($campo, self::CAMPOS_GUIAS, true)) {
            return self::DUENO_GUIAS;
        }

        return null;
    }

    /**
     * Dueño más temprano en la cola (prioridad vendedora → auxiliar → guías).
     *
     * @param  list<string>  $campos
     */
    public static function duenoActivo(array $campos): ?string
    {
        $campos = self::filtrar($campos);
        foreach (self::PRIORIDAD_DUENOS as $dueno) {
            if (self::camposDeDueno($campos, $dueno) !== []) {
                return $dueno;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $campos
     * @return list<string>
     */
    public static function camposDeDueno(array $campos, string $dueno): array
    {
        $grupo = match ($dueno) {
            self::DUENO_VENDEDORA => self::CAMPOS_VENDEDORA,
            self::DUENO_AUXILIAR => self::CAMPOS_AUXILIAR,
            self::DUENO_GUIAS => self::CAMPOS_GUIAS,
            default => [],
        };

        return array_values(array_intersect(self::filtrar($campos), $grupo));
    }

    /**
     * @param  list<string>  $campos
     * @return list<string>
     */
    public static function quitarCamposDeDueno(array $campos, string $dueno): array
    {
        $quitar = self::camposDeDueno($campos, $dueno);

        return array_values(array_diff(self::filtrar($campos), $quitar));
    }

    /**
     * @return array{
     *     fase: string,
     *     permisos: list<string>,
     *     incluir_vendedora: bool,
     *     tipo_alerta: string,
     *     url_path: string,
     *     etiqueta: string
     * }
     */
    public static function destinoPara(string $dueno): array
    {
        return match ($dueno) {
            self::DUENO_VENDEDORA => [
                'fase' => CatalogoEstatusPedido::FASE_RECHAZADO_VENDEDORA,
                'permisos' => [],
                'incluir_vendedora' => true,
                'tipo_alerta' => 'pedido_error_datos',
                'url_path' => '/control-pedidos?tab=RECHAZADAS',
                'etiqueta' => 'vendedora',
            ],
            self::DUENO_AUXILIAR => [
                'fase' => CatalogoEstatusPedido::FASE_PENDIENTE_AUXILIAR,
                'permisos' => ['control_pedidos.auditar'],
                'incluir_vendedora' => false,
                'tipo_alerta' => 'pedido_error_remision',
                'url_path' => '/control-pedidos/auditar?tab=PENDIENTES',
                'etiqueta' => 'auxiliar',
            ],
            self::DUENO_GUIAS => [
                'fase' => CatalogoEstatusPedido::FASE_PENDIENTE_DE_GUIA,
                'permisos' => [
                    'control_pedidos.delegado',
                    'control_pedidos.cedis',
                    'control_pedidos.auditar',
                ],
                'incluir_vendedora' => true,
                'tipo_alerta' => 'pedido_error_guia',
                'url_path' => '/control-pedidos/delegado?tab=PENDIENTES_GUIA',
                'etiqueta' => 'encargado de guías',
            ],
            default => throw new \InvalidArgumentException("Dueño de error desconocido: {$dueno}"),
        };
    }

    /**
     * @param  list<string>  $campos
     * @return list<string>
     */
    public static function etiquetasDe(array $campos): array
    {
        return array_map(
            fn (string $k) => self::ETIQUETAS[$k] ?? $k,
            self::filtrar($campos)
        );
    }

    /**
     * Dueños presentes en la cola, en orden de prioridad.
     *
     * @param  list<string>  $campos
     * @return list<string>
     */
    public static function duenosEnCola(array $campos): array
    {
        $presentes = [];
        foreach (self::PRIORIDAD_DUENOS as $dueno) {
            if (self::camposDeDueno($campos, $dueno) !== []) {
                $presentes[] = $dueno;
            }
        }

        return $presentes;
    }
}
