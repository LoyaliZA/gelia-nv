<?php

namespace App\Support\ControlPedidos;

use App\Models\ControlPedidos\CatalogoEstatusPedido;

class CamposIncorrectosPedidoBma
{
    public const DUENO_VENDEDORA = 'vendedora';

    public const DUENO_AUXILIAR = 'auxiliar';

    public const DUENO_CEDIS = 'cedis';

    public const DUENO_GUIAS = 'guias';

    /** Prioridad de atención: más temprano primero. */
    public const PRIORIDAD_DUENOS = [
        self::DUENO_VENDEDORA,
        self::DUENO_AUXILIAR,
        self::DUENO_CEDIS,
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
        'origen',
        'cliente',
        'fecha',
        'banco',
        'almacen',
        'es_resguardo',
        'modo_resguardo',
        'total_mercancia',
        'costo_envio',
        'costo_seguro',
        'total_a_cobrar',
        'aplica_seguro',
        'saldo_a_favor',
        'reexpedicion',
        'cliente_proporciona_guia',
        'anexar_remision',
        'comentarios_drive',
        'comprobantes',
        'pagos',
        'envio_tienda',
    ];

    public const CAMPOS_AUXILIAR = [
        'remision',
        'folio_remision',
        'pago_validado',
        'anexo_envio',
    ];

    public const CAMPOS_CEDIS = [
        'empaque',
        'producto_faltante',
        'producto_danado',
        'inventario',
        'tipo_caja',
        'numero_cajas',
        'peso_real',
        'peso_volumetrico',
        'peso_cobrado',
        'apartado_resguardo',
    ];

    public const CAMPOS_GUIAS = [
        'numero_rastreo',
        'guia_pdf',
    ];

    public const ALLOWLIST = [
        ...self::CAMPOS_VENDEDORA,
        ...self::CAMPOS_AUXILIAR,
        ...self::CAMPOS_CEDIS,
        ...self::CAMPOS_GUIAS,
    ];

    /** Solo aplican si el origen requiere logística (envío foráneo / paquetería). */
    public const CAMPOS_SOLO_LOGISTICA = [
        'paqueteria',
        'tipo_guia',
        'referencia',
        'codigo_postal',
        'ciudad_estado',
        'domicilio',
        'destinatario',
        'telefono',
        'costo_envio',
        'costo_seguro',
        'aplica_seguro',
        'reexpedicion',
        'cliente_proporciona_guia',
        'tipo_caja',
        'numero_cajas',
        'peso_real',
        'peso_volumetrico',
        'peso_cobrado',
        ...self::CAMPOS_GUIAS,
    ];

    /** Solo aplican si el origen NO requiere logística (tienda / PDV). */
    public const CAMPOS_SOLO_TIENDA = [
        'envio_tienda',
    ];

    /** Solo aplican si el pedido está en resguardo. */
    public const CAMPOS_SOLO_RESGUARDO = [
        'apartado_resguardo',
        'modo_resguardo',
    ];

    /** Campos de envío / guía que invalidan la guía capturada. */
    public const INVALIDAN_GUIA = [
        'domicilio',
        'destinatario',
        'telefono',
        'paqueteria',
        'tipo_guia',
        'referencia',
        'codigo_postal',
        'ciudad_estado',
        'cliente_proporciona_guia',
        'envio_tienda',
        ...self::CAMPOS_GUIAS,
    ];

    public const INVALIDAN_REMISION = [
        'remision',
        'folio_remision',
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
        'origen' => 'Origen',
        'cliente' => 'Cliente',
        'fecha' => 'Fecha',
        'banco' => 'Banco',
        'almacen' => 'Almacén de salida',
        'es_resguardo' => 'Resguardo',
        'modo_resguardo' => 'Tipo de resguardo',
        'total_mercancia' => 'Total mercancía',
        'costo_envio' => 'Costo de envío',
        'costo_seguro' => 'Costo de seguro',
        'total_a_cobrar' => 'Total a cobrar',
        'aplica_seguro' => 'Aplica seguro',
        'saldo_a_favor' => 'Saldo a favor',
        'reexpedicion' => 'Reexpedición',
        'cliente_proporciona_guia' => 'Guía del cliente',
        'anexar_remision' => 'Anexar remisión',
        'comentarios_drive' => 'Comentarios Drive / almacén',
        'comprobantes' => 'Comprobantes',
        'pagos' => 'Pagos / exhibición',
        'envio_tienda' => 'Envío de tienda',
        'remision' => 'Remisión PDF',
        'folio_remision' => 'Folio de remisión',
        'pago_validado' => 'Validación de pago',
        'anexo_envio' => 'Anexo de envío',
        'empaque' => 'Empaque',
        'producto_faltante' => 'Producto faltante',
        'producto_danado' => 'Producto dañado',
        'inventario' => 'Inventario',
        'tipo_caja' => 'Tipo de caja',
        'numero_cajas' => 'Número de cajas',
        'peso_real' => 'Peso real',
        'peso_volumetrico' => 'Peso volumétrico',
        'peso_cobrado' => 'Peso cobrado',
        'apartado_resguardo' => 'Apartado de resguardo',
        'numero_rastreo' => 'Número de guía',
        'guia_pdf' => 'PDF de guía',
    ];

    public static function filtrar(array $campos): array
    {
        return array_values(array_unique(array_intersect($campos, self::ALLOWLIST)));
    }

    /**
     * Allowlist según capacidades del origen / pedido (no por nombre de origen).
     *
     * @return list<string>
     */
    public static function allowlistParaPedido(?\App\Models\ControlPedidos\PedidoBma $pedido): array
    {
        $pedido?->loadMissing('origen');
        $requiereLogistica = (bool) ($pedido?->origen?->requiere_logistica ?? true);
        $esResguardo = (bool) ($pedido?->es_resguardo ?? false);

        return self::allowlistParaContexto($requiereLogistica, $esResguardo);
    }

    /**
     * @return list<string>
     */
    public static function allowlistParaContexto(bool $requiereLogistica, bool $esResguardo = false): array
    {
        $excluir = [];

        if ($requiereLogistica) {
            $excluir = array_merge($excluir, self::CAMPOS_SOLO_TIENDA);
        } else {
            $excluir = array_merge($excluir, self::CAMPOS_SOLO_LOGISTICA);
        }

        if (! $esResguardo) {
            $excluir = array_merge($excluir, self::CAMPOS_SOLO_RESGUARDO);
        }

        return array_values(array_diff(self::ALLOWLIST, $excluir));
    }

    /**
     * @param  list<string>  $campos
     * @return list<string>
     */
    public static function filtrarParaPedido(array $campos, ?\App\Models\ControlPedidos\PedidoBma $pedido): array
    {
        return array_values(array_unique(array_intersect(
            self::filtrar($campos),
            self::allowlistParaPedido($pedido)
        )));
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
        if (in_array($campo, self::CAMPOS_CEDIS, true)) {
            return self::DUENO_CEDIS;
        }
        if (in_array($campo, self::CAMPOS_GUIAS, true)) {
            return self::DUENO_GUIAS;
        }

        return null;
    }

    /**
     * Dueño más temprano en la cola (prioridad vendedora → auxiliar → cedis → guías).
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
            self::DUENO_CEDIS => self::CAMPOS_CEDIS,
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
            self::DUENO_CEDIS => [
                'fase' => CatalogoEstatusPedido::FASE_INCIDENCIA_CEDIS,
                'permisos' => ['control_pedidos.cedis'],
                'incluir_vendedora' => false,
                'tipo_alerta' => 'pedido_error_cedis',
                'url_path' => '/control-pedidos/cedis?tab=INCORRECTAS',
                'etiqueta' => 'CEDIS',
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
