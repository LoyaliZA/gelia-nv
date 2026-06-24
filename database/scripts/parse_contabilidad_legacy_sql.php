<?php

/**
 * One-time script: parse legacy SQL dump into database/data/contabilidad_legacy.json
 *
 * Usage: php database/scripts/parse_contabilidad_legacy_sql.php
 */

$sqlPath = dirname(__DIR__, 2) . '/Legacy/contabilidad_bellaroma/contabilidad_20260624_134159.sql';
$outPath = dirname(__DIR__) . '/data/contabilidad_legacy.json';

if (! is_file($sqlPath)) {
    fwrite(STDERR, "SQL dump not found: {$sqlPath}\n");
    exit(1);
}

$sql = file_get_contents($sqlPath);

function extractInsertBlock(string $sql, string $table): ?string
{
    $pattern = '/INSERT INTO `' . preg_quote($table, '/') . '` VALUES\s*(.+?);/s';
    if (! preg_match($pattern, $sql, $matches)) {
        return null;
    }

    return trim($matches[1]);
}

function parseMysqlValueTuples(string $block): array
{
    $rows = [];
    $length = strlen($block);
    $i = 0;

    while ($i < $length) {
        if ($block[$i] !== '(') {
            $i++;
            continue;
        }

        $i++;
        $values = [];
        $current = '';
        $inString = false;
        $escape = false;

        while ($i < $length) {
            $char = $block[$i];

            if ($escape) {
                $current .= $char;
                $escape = false;
                $i++;
                continue;
            }

            if ($char === '\\' && $inString) {
                $escape = true;
                $current .= $char;
                $i++;
                continue;
            }

            if ($char === "'") {
                $inString = ! $inString;
                $i++;
                continue;
            }

            if ($inString) {
                $current .= $char;
                $i++;
                continue;
            }

            if ($char === ')') {
                $values[] = normalizeMysqlScalar(trim($current));
                $rows[] = $values;
                $i++;
                break;
            }

            if ($char === ',') {
                $values[] = normalizeMysqlScalar(trim($current));
                $current = '';
                $i++;
                continue;
            }

            $current .= $char;
            $i++;
        }
    }

    return $rows;
}

function normalizeMysqlScalar(string $value): mixed
{
    if ($value === 'NULL') {
        return null;
    }

    if (is_numeric($value)) {
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    return $value;
}

function mapPlatforms(array $rows): array
{
    return array_map(static function (array $row) {
        return [
            'id' => $row[0],
            'nombre' => $row[1],
            'frecuencia_pago' => $row[2],
            'ultimo_corte' => $row[3],
            'dias_personalizados' => $row[4],
            'tasa_comision_pct' => $row[5],
            'cuota_fija' => $row[6],
            'tasa_iva_pct' => $row[7],
            'activo' => (bool) $row[8],
            'created_at' => $row[9],
            'updated_at' => $row[10],
        ];
    }, $rows);
}

function mapLotes(array $rows): array
{
    return array_map(static function (array $row) {
        return [
            'id' => $row[0],
            'plataforma_pago_id' => $row[1],
            'fecha_corte_esperada' => $row[2],
            'fecha_deposito_real' => $row[3],
            'monto_ventas_total' => $row[4],
            'comisiones_plataforma_total' => $row[5],
            'monto_esperado_banco' => $row[6],
            'monto_real_banco' => $row[7],
            'estatus' => $row[8],
            'factura_referencia' => $row[9],
            'created_at' => $row[10],
            'updated_at' => $row[11],
        ];
    }, $rows);
}

function mapPedidos(array $rows): array
{
    return array_map(static function (array $row) {
        return [
            'id' => $row[0],
            'fecha_salida' => $row[1],
            'numero_pedido' => $row[2],
            'cliente_nombre' => $row[3],
            'tipo_transaccion' => $row[4],
            'plataforma_pago_id' => $row[5],
            'lote_pago_id' => $row[6],
            'venta_total' => $row[7],
            'costo_envio' => $row[8],
            'envio_pagado_cliente' => (bool) $row[9],
            'comision_plataforma' => $row[10],
            'utilidad_total' => $row[11],
            'estatus_pago' => $row[12],
            'comision_transferencia' => $row[13],
            'fecha_retiro' => $row[14],
            'bloqueado' => (bool) $row[15],
            'created_at' => $row[16],
            'updated_at' => $row[17],
        ];
    }, $rows);
}

function mapLineas(array $rows): array
{
    return array_map(static function (array $row) {
        return [
            'id' => $row[0],
            'pedido_id' => $row[1],
            'sku' => $row[2],
            'piezas' => $row[3],
            'nombre_producto' => $row[4],
            'precio_unitario' => $row[5],
            'subtotal' => $row[6],
            'created_at' => $row[7],
            'updated_at' => $row[8],
        ];
    }, $rows);
}

$tables = [
    'platforms' => 'plataformas',
    'lotes_pagos' => 'lotes',
    'contabilidad_pedidos' => 'pedidos',
    'contabilidad_pedido_detalles' => 'lineas',
];

$payload = [
    'source' => 'Legacy/contabilidad_bellaroma/contabilidad_20260624_134159.sql',
    'generated_at' => date('c'),
    'meta' => [],
];

$mappers = [
    'plataformas' => 'mapPlatforms',
    'lotes' => 'mapLotes',
    'pedidos' => 'mapPedidos',
    'lineas' => 'mapLineas',
];

foreach ($tables as $sqlTable => $jsonKey) {
    $block = extractInsertBlock($sql, $sqlTable);
    if ($block === null) {
        fwrite(STDERR, "No INSERT found for {$sqlTable}\n");
        exit(1);
    }

    $rows = parseMysqlValueTuples($block);
    $mapper = $mappers[$jsonKey];
    $payload[$jsonKey] = $mapper($rows);
    $payload['meta'][$jsonKey] = count($payload[$jsonKey]);
}

$ventaTotal = array_sum(array_column($payload['pedidos'], 'venta_total'));
$payload['meta']['venta_total_sum'] = round($ventaTotal, 2);

file_put_contents(
    $outPath,
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
);

echo "Wrote {$outPath}\n";
foreach ($payload['meta'] as $key => $count) {
    echo "  {$key}: {$count}\n";
}
