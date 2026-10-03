<?php

namespace App\Support\PuntoVenta\Resguardos;

/**
 * Contrato del paquete de resguardos ya adaptados a GELIA para restaurarlos en otro entorno.
 */
final class PaqueteResguardosGelia
{
    public const ORIGEN = 'TERA';

    public const PREFIJO = '_imp_';

    /**
     * @return list<string>
     */
    public static function tablas(): array
    {
        return [
            'pdv_resguardos',
            'pdv_resguardo_bultos',
            'pdv_resguardo_eventos',
            'pdv_resguardo_entregas',
            'pdv_resguardo_entrega_bultos',
            'pdv_resguardo_evidencias',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function columnas(): array
    {
        return [
            'pdv_resguardos' => [
                'id', 'pedido_bma_id', 'cliente_id', 'sucursal_id', 'almacen_id', 'estado',
                'cantidad_bultos_esperada', 'salida_cedis_at', 'recepcion_fisica_at', 'custodia_confirmada_at',
                'entrega_completada_at', 'devolucion_confirmada_at', 'vencido_repuesto_at', 'entrega_bloqueada',
                'snapshot_folio', 'snapshot_cliente_nombre', 'snapshot_json', 'version', 'es_demo',
                'created_at', 'updated_at',
            ],
            'pdv_resguardo_bultos' => [
                'id', 'resguardo_id', 'pedido_bma_id', 'folio', 'codigo_etiqueta', 'tipo', 'piezas', 'condicion',
                'estado', 'recepcion_at', 'recepcion_por_id', 'custodia_at', 'custodia_por_id', 'entrega_at',
                'devolucion_salida_at', 'version', 'created_at', 'updated_at',
            ],
            'pdv_resguardo_eventos' => [
                'id', 'resguardo_id', 'bulto_id', 'tipo_evento', 'estado_anterior', 'estado_nuevo', 'actor_id',
                'ocurrido_at', 'snapshot_json', 'idempotency_key', 'created_at', 'updated_at',
            ],
            'pdv_resguardo_entregas' => [
                'id', 'resguardo_id', 'pedido_bma_id', 'relacion', 'nombre_quien_retira', 'entregado_por_id',
                'entregado_at', 'incidencia_autorizada_id', 'snapshot_json', 'idempotency_key', 'version',
                'created_at', 'updated_at',
            ],
            'pdv_resguardo_entrega_bultos' => [
                'id', 'entrega_id', 'bulto_id', 'created_at', 'updated_at',
            ],
            'pdv_resguardo_evidencias' => [
                'id', 'resguardo_id', 'evento_id', 'bulto_id', 'incidencia_id', 'entrega_id', 'tipo',
                'ruta_interna', 'nombre_original', 'mime_type', 'tamano_bytes', 'hash_sha256', 'actor_id',
                'capturado_at', 'inmutable', 'metadata_json', 'created_at', 'updated_at',
            ],
        ];
    }

    public static function ddl(): string
    {
        return <<<'SQL'
CREATE TABLE `_imp_pdv_resguardos` (
  `id` bigint unsigned NOT NULL,
  `pedido_bma_id` bigint unsigned DEFAULT NULL,
  `cliente_id` bigint unsigned DEFAULT NULL,
  `sucursal_id` bigint unsigned NOT NULL,
  `almacen_id` bigint unsigned DEFAULT NULL,
  `estado` varchar(32) NOT NULL,
  `cantidad_bultos_esperada` smallint unsigned NOT NULL DEFAULT 0,
  `salida_cedis_at` datetime DEFAULT NULL,
  `recepcion_fisica_at` datetime DEFAULT NULL,
  `custodia_confirmada_at` datetime DEFAULT NULL,
  `entrega_completada_at` datetime DEFAULT NULL,
  `devolucion_confirmada_at` datetime DEFAULT NULL,
  `vencido_repuesto_at` datetime DEFAULT NULL,
  `entrega_bloqueada` tinyint(1) NOT NULL DEFAULT 0,
  `snapshot_folio` varchar(64) DEFAULT NULL,
  `snapshot_cliente_nombre` varchar(255) DEFAULT NULL,
  `snapshot_json` longtext,
  `version` int unsigned NOT NULL DEFAULT 1,
  `es_demo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `_imp_pdv_resguardo_bultos` (
  `id` bigint unsigned NOT NULL,
  `resguardo_id` bigint unsigned NOT NULL,
  `pedido_bma_id` bigint unsigned DEFAULT NULL,
  `folio` varchar(64) DEFAULT NULL,
  `codigo_etiqueta` varchar(16) DEFAULT NULL,
  `tipo` varchar(16) NOT NULL,
  `piezas` int unsigned NOT NULL DEFAULT 1,
  `condicion` varchar(32) DEFAULT NULL,
  `estado` varchar(32) NOT NULL,
  `recepcion_at` datetime DEFAULT NULL,
  `recepcion_por_id` bigint unsigned DEFAULT NULL,
  `custodia_at` datetime DEFAULT NULL,
  `custodia_por_id` bigint unsigned DEFAULT NULL,
  `entrega_at` datetime DEFAULT NULL,
  `devolucion_salida_at` datetime DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `_imp_bultos_resguardo_idx` (`resguardo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `_imp_pdv_resguardo_eventos` (
  `id` bigint unsigned NOT NULL,
  `resguardo_id` bigint unsigned NOT NULL,
  `bulto_id` bigint unsigned DEFAULT NULL,
  `tipo_evento` varchar(64) NOT NULL,
  `estado_anterior` varchar(32) DEFAULT NULL,
  `estado_nuevo` varchar(32) DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `ocurrido_at` datetime NOT NULL,
  `snapshot_json` longtext,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `_imp_eventos_resguardo_idx` (`resguardo_id`),
  KEY `_imp_eventos_clave_idx` (`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `_imp_pdv_resguardo_entregas` (
  `id` bigint unsigned NOT NULL,
  `resguardo_id` bigint unsigned NOT NULL,
  `pedido_bma_id` bigint unsigned DEFAULT NULL,
  `relacion` varchar(16) NOT NULL,
  `nombre_quien_retira` varchar(255) NOT NULL,
  `entregado_por_id` bigint unsigned DEFAULT NULL,
  `entregado_at` datetime NOT NULL,
  `incidencia_autorizada_id` bigint unsigned DEFAULT NULL,
  `snapshot_json` longtext,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `_imp_entregas_resguardo_idx` (`resguardo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `_imp_pdv_resguardo_entrega_bultos` (
  `id` bigint unsigned NOT NULL,
  `entrega_id` bigint unsigned NOT NULL,
  `bulto_id` bigint unsigned NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `_imp_entrega_bultos_entrega_idx` (`entrega_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `_imp_pdv_resguardo_evidencias` (
  `id` bigint unsigned NOT NULL,
  `resguardo_id` bigint unsigned NOT NULL,
  `evento_id` bigint unsigned DEFAULT NULL,
  `bulto_id` bigint unsigned DEFAULT NULL,
  `incidencia_id` bigint unsigned DEFAULT NULL,
  `entrega_id` bigint unsigned DEFAULT NULL,
  `tipo` varchar(16) NOT NULL,
  `ruta_interna` varchar(255) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `mime_type` varchar(128) DEFAULT NULL,
  `tamano_bytes` bigint unsigned DEFAULT NULL,
  `hash_sha256` varchar(64) NOT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `capturado_at` datetime NOT NULL,
  `inmutable` tinyint(1) NOT NULL DEFAULT 1,
  `metadata_json` longtext,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `_imp_evidencias_resguardo_idx` (`resguardo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }
}
