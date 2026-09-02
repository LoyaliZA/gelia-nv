<?php

namespace App\Support\PuntoVenta\Resguardos;

use App\Models\PuntoVenta\ResguardoPdvEvento;

final class TraductorMetadataEventoResguardoPdv
{
    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return list<array{clave: string, etiqueta: string, valor: string}>
     */
    public static function metadataLegible(
        ?array $snapshot,
        string $tipoEvento,
        bool $verDatosOperativos,
        bool $verDetalleCompleto,
    ): array {
        if ($snapshot === null || $snapshot === []) {
            return [];
        }

        $filas = [];

        foreach ($snapshot as $clave => $valor) {
            if ($valor === null || $valor === '' || $valor === []) {
                continue;
            }

            $traducido = self::traducirEntrada(
                (string) $clave,
                $valor,
                $tipoEvento,
                $verDatosOperativos,
                $verDetalleCompleto,
            );

            if ($traducido !== null) {
                $filas[] = $traducido;
            }
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return array<string, mixed>|null
     */
    public static function metadataOriginal(
        ?array $snapshot,
        bool $verDetalleCompleto,
    ): ?array {
        if ($snapshot === null || $snapshot === []) {
            return null;
        }

        if ($verDetalleCompleto) {
            return $snapshot;
        }

        return self::enmascararSnapshot($snapshot);
    }

    /**
     * @param  array<string, mixed>  $integracion
     * @return list<array{clave: string, etiqueta: string, valor: string}>
     */
    public static function integracionLegible(array $integracion, bool $verDetalleCompleto): array
    {
        $filas = [];

        $estado = (string) ($integracion['estado'] ?? 'desconocido');
        $filas[] = [
            'clave' => 'integracion_cp.estado',
            'etiqueta' => 'Estado integración CP',
            'valor' => match ($estado) {
                'completada' => 'Completada',
                'pendiente' => 'Pendiente',
                default => $estado,
            },
        ];

        if (! empty($integracion['completada_at'])) {
            $filas[] = [
                'clave' => 'integracion_cp.completada_at',
                'etiqueta' => 'Integración completada',
                'valor' => (string) $integracion['completada_at'],
            ];
        }

        if (! empty($integracion['ultimo_intento_at'])) {
            $filas[] = [
                'clave' => 'integracion_cp.ultimo_intento_at',
                'etiqueta' => 'Último intento',
                'valor' => (string) $integracion['ultimo_intento_at'],
            ];
        }

        if (! empty($integracion['intentos'])) {
            $filas[] = [
                'clave' => 'integracion_cp.intentos',
                'etiqueta' => 'Intentos',
                'valor' => (string) (int) $integracion['intentos'],
            ];
        }

        if (! empty($integracion['ultimo_error'])) {
            $filas[] = [
                'clave' => 'integracion_cp.ultimo_error',
                'etiqueta' => 'Último error',
                'valor' => $verDetalleCompleto
                    ? (string) $integracion['ultimo_error']
                    : 'Error de integración registrado (detalle restringido)',
            ];
        }

        return $filas;
    }

    public static function categoriaEvento(string $tipoEvento): string
    {
        return match (true) {
            str_contains($tipoEvento, 'incidencia') => 'incidencia',
            str_contains($tipoEvento, 'entrega') => 'entrega',
            str_contains($tipoEvento, 'devolucion') => 'devolucion',
            str_contains($tipoEvento, 'correccion') => 'correccion',
            str_contains($tipoEvento, 'recepcion') => 'recepcion',
            in_array($tipoEvento, [
                ResguardoPdvEvento::TIPO_MARCADO_VENCIDO,
                ResguardoPdvEvento::TIPO_MARCADO_REZAGADO,
                ResguardoPdvEvento::TIPO_VENCIDO_REPUESTO,
                ResguardoPdvEvento::TIPO_CANCELACION_RECIBIDA,
                ResguardoPdvEvento::TIPO_ETIQUETAS_GENERADAS,
            ], true) => 'sistema',
            default => 'operacion',
        };
    }

    /**
     * @return array{clave: string, etiqueta: string, valor: string}|null
     */
    private static function traducirEntrada(
        string $clave,
        mixed $valor,
        string $tipoEvento,
        bool $verDatosOperativos,
        bool $verDetalleCompleto,
    ): ?array {
        if ($clave === 'integracion_cp' && is_array($valor)) {
            return null;
        }

        if (in_array($clave, ['idempotency_key'], true) && ! $verDetalleCompleto) {
            return null;
        }

        $etiqueta = self::etiquetaCampo($clave);

        return match ($clave) {
            'receptor' => is_array($valor)
                ? self::traducirReceptor($valor, $verDatosOperativos)
                : null,
            'bultos' => is_array($valor)
                ? [
                    'clave' => $clave,
                    'etiqueta' => $etiqueta,
                    'valor' => self::formatearBultos($valor),
                ]
                : null,
            'valores_anteriores', 'valores_nuevos' => is_array($valor)
                ? [
                    'clave' => $clave,
                    'etiqueta' => $etiqueta,
                    'valor' => self::formatearPares($valor, $verDetalleCompleto),
                ]
                : null,
            'recepcion_completa' => [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'valor' => $valor ? 'Sí' : 'No',
            ],
            'incidencia_tipo' => [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'valor' => EtiquetasResguardoPdv::tiposIncidencia()[(string) $valor] ?? (string) $valor,
            ],
            'tipo_correccion' => [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'valor' => CorreccionResguardoPdv::etiquetas()[(string) $valor] ?? (string) $valor,
            ],
            'handoff' => [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'valor' => $valor === 'envio' ? 'Salida CEDIS' : (string) $valor,
            ],
            'snapshot_cliente_nombre' => $verDetalleCompleto
                ? ['clave' => $clave, 'etiqueta' => $etiqueta, 'valor' => (string) $valor]
                : null,
            default => [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'valor' => is_scalar($valor) ? (string) $valor : json_encode($valor, JSON_UNESCAPED_UNICODE),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $receptor
     * @return array{clave: string, etiqueta: string, valor: string}
     */
    private static function traducirReceptor(array $receptor, bool $verDatosOperativos): array
    {
        $relacion = (string) ($receptor['relacion'] ?? '');
        $relacionEtiqueta = EtiquetasResguardoPdv::relacionesEntrega()[$relacion] ?? $relacion;

        if ($verDatosOperativos && filled($receptor['nombre'] ?? null)) {
            $valor = (string) $receptor['nombre'].' ('.$relacionEtiqueta.')';
        } else {
            $valor = 'Registrado ('.$relacionEtiqueta.')';
        }

        return [
            'clave' => 'receptor',
            'etiqueta' => 'Persona que retira',
            'valor' => $valor,
        ];
    }

    /**
     * @param  list<array<string, mixed>>|array<int, mixed>  $bultos
     */
    private static function formatearBultos(array $bultos): string
    {
        $folios = [];
        foreach ($bultos as $bulto) {
            if (! is_array($bulto)) {
                continue;
            }
            $folios[] = (string) ($bulto['folio'] ?? $bulto['id'] ?? '?');
        }

        if ($folios === []) {
            return '—';
        }

        return implode(', ', $folios);
    }

    /**
     * @param  array<string, mixed>  $pares
     */
    private static function formatearPares(array $pares, bool $verDetalleCompleto): string
    {
        $partes = [];
        foreach ($pares as $campo => $valor) {
            if ($campo === 'snapshot_cliente_nombre' && ! $verDetalleCompleto) {
                $partes[] = self::etiquetaCampo($campo).': [restringido]';

                continue;
            }
            $partes[] = self::etiquetaCampo((string) $campo).': '.(is_scalar($valor) ? (string) $valor : json_encode($valor));
        }

        return $partes === [] ? '—' : implode(' · ', $partes);
    }

    private static function etiquetaCampo(string $clave): string
    {
        return match ($clave) {
            'almacen_id' => 'Almacén (ID)',
            'almacen_codigo' => 'Almacén',
            'cantidad_llegada' => 'Bultos en llegada',
            'cantidad_recibida' => 'Bultos recibidos',
            'cantidad_esperada' => 'Bultos esperados',
            'cantidad_pendiente' => 'Bultos pendientes',
            'cantidad_entregada' => 'Bultos entregados',
            'cantidad_devuelta' => 'Bultos devueltos',
            'recepcion_completa' => 'Recepción completa',
            'incidencia_id' => 'Incidencia',
            'incidencia_tipo' => 'Tipo de incidencia',
            'descripcion' => 'Descripción',
            'descripcion_original' => 'Descripción original',
            'motivo' => 'Motivo',
            'motivo_autorizacion' => 'Motivo de autorización',
            'entrega_id' => 'Entrega',
            'evento_referencia_id' => 'Evento de referencia',
            'metodo_validacion' => 'Método de validación',
            'observaciones' => 'Observaciones',
            'pedido_bma_id' => 'Pedido BMA',
            'sucursal_id' => 'Sucursal',
            'handoff' => 'Origen',
            'folio' => 'Folio',
            'folio_remision' => 'Remisión',
            'bulto_id' => 'Bulto',
            'bulto_folio' => 'Folio de bulto',
            'tipo_correccion' => 'Tipo de corrección',
            'valores_anteriores' => 'Valores anteriores',
            'valores_nuevos' => 'Valores nuevos',
            'anotacion' => 'Anotación',
            'etiquetas_generadas' => 'Etiquetas generadas',
            'codigos' => 'Códigos de etiqueta',
            default => str_replace('_', ' ', ucfirst($clave)),
        };
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private static function enmascararSnapshot(array $snapshot): array
    {
        $enmascarado = $snapshot;

        if (isset($enmascarado['integracion_cp']) && is_array($enmascarado['integracion_cp'])) {
            $integracion = $enmascarado['integracion_cp'];
            unset($integracion['idempotency_key']);
            if (! empty($integracion['ultimo_error'])) {
                $integracion['ultimo_error'] = '[restringido]';
            }
            $enmascarado['integracion_cp'] = $integracion;
        }

        if (isset($enmascarado['receptor']) && is_array($enmascarado['receptor'])) {
            $enmascarado['receptor'] = [
                'relacion' => $enmascarado['receptor']['relacion'] ?? null,
                'nombre' => '[restringido]',
            ];
        }

        foreach (['valores_anteriores', 'valores_nuevos'] as $bloque) {
            if (isset($enmascarado[$bloque]['snapshot_cliente_nombre'])) {
                $enmascarado[$bloque]['snapshot_cliente_nombre'] = '[restringido]';
            }
        }

        unset($enmascarado['idempotency_key']);

        return $enmascarado;
    }
}
