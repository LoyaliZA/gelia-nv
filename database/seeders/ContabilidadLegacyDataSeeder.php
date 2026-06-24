<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ContabilidadLegacyDataSeeder extends Seeder
{
    private array $frecuenciaMap = [
        'inmediato' => 1,
        'diario' => 2,
        'semanal' => 3,
        'quincenal' => 4,
        'personalizado' => 5,
    ];

    private array $estatusMap = [
        'pendiente' => 1,
        'transferido' => 2,
    ];

    public function run(): void
    {
        $path = database_path('data/contabilidad_legacy.json');
        if (! File::exists($path)) {
            throw new \RuntimeException("Legacy data file not found: {$path}");
        }

        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        DB::table('contabilidad_pedido_lineas')->truncate();
        DB::table('contabilidad_pedidos')->truncate();
        DB::table('contabilidad_lotes_pago')->truncate();
        DB::table('contabilidad_plataformas_pago')->truncate();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $plataformasIndex = [];
        foreach ($payload['plataformas'] as $row) {
            $frecuenciaCodigo = strtolower((string) $row['frecuencia_pago']);
            $frecuenciaId = $this->frecuenciaMap[$frecuenciaCodigo] ?? 5;

            DB::table('contabilidad_plataformas_pago')->insert([
                'id' => $row['id'],
                'nombre' => $row['nombre'],
                'frecuencia_pago_id' => $frecuenciaId,
                'ultimo_corte' => $row['ultimo_corte'],
                'dias_personalizados' => $row['dias_personalizados'],
                'tasa_comision_pct' => $row['tasa_comision_pct'],
                'cuota_fija' => $row['cuota_fija'],
                'tasa_iva_pct' => $row['tasa_iva_pct'],
                'activo' => $row['activo'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);

            $plataformasIndex[$row['id']] = $row;
        }

        foreach ($payload['lotes'] as $row) {
            DB::table('contabilidad_lotes_pago')->insert([
                'id' => $row['id'],
                'plataforma_pago_id' => $row['plataforma_pago_id'],
                'fecha_corte_esperada' => $row['fecha_corte_esperada'],
                'fecha_deposito_real' => $row['fecha_deposito_real'],
                'monto_ventas_total' => $row['monto_ventas_total'],
                'comisiones_plataforma_total' => $row['comisiones_plataforma_total'],
                'monto_esperado_banco' => $row['monto_esperado_banco'],
                'monto_real_banco' => $row['monto_real_banco'],
                'estatus' => $row['estatus'],
                'factura_referencia' => $row['factura_referencia'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
        }

        foreach ($payload['pedidos'] as $row) {
            $plataforma = $plataformasIndex[$row['plataforma_pago_id']] ?? null;
            if (! $plataforma) {
                throw new \RuntimeException("Plataforma no encontrada para pedido {$row['id']}");
            }

            $desglose = $this->calcularDesgloseComision(
                (float) $row['venta_total'],
                (float) $plataforma['tasa_comision_pct'],
                (float) $plataforma['cuota_fija'],
                (float) $plataforma['tasa_iva_pct']
            );

            if (abs($desglose['comision_total'] - (float) $row['comision_plataforma']) > 0.01) {
                $desglose = $this->desglosarComisionAlmacenada(
                    (float) $row['comision_plataforma'],
                    (float) $plataforma['tasa_iva_pct']
                );
            }

            $tipoCodigo = strtolower((string) $row['tipo_transaccion']);
            $tipoId = match (true) {
                str_contains($tipoCodigo, 'contracargo') => 2,
                str_contains($tipoCodigo, 'reembolso') => 3,
                default => 1,
            };

            $estatusCodigo = strtolower((string) $row['estatus_pago']);
            $estatusId = $this->estatusMap[$estatusCodigo] ?? 1;

            DB::table('contabilidad_pedidos')->insert([
                'id' => $row['id'],
                'fecha_salida' => $row['fecha_salida'],
                'numero_pedido' => $row['numero_pedido'],
                'cliente_nombre' => $row['cliente_nombre'],
                'tipo_transaccion_id' => $tipoId,
                'plataforma_pago_id' => $row['plataforma_pago_id'],
                'lote_pago_id' => $row['lote_pago_id'],
                'venta_total' => $row['venta_total'],
                'costo_envio' => $row['costo_envio'],
                'envio_pagado_cliente' => $row['envio_pagado_cliente'],
                'comision_base' => $desglose['comision_base'],
                'comision_iva' => $desglose['comision_iva'],
                'tasa_comision_pct' => $plataforma['tasa_comision_pct'],
                'cuota_fija' => $plataforma['cuota_fija'],
                'comision_plataforma' => $row['comision_plataforma'],
                'utilidad_total' => $row['utilidad_total'],
                'estatus_pago_id' => $estatusId,
                'comision_transferencia' => $row['comision_transferencia'],
                'fecha_retiro' => $row['fecha_retiro'],
                'bloqueado' => $row['bloqueado'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
        }

        foreach ($payload['lineas'] as $row) {
            DB::table('contabilidad_pedido_lineas')->insert([
                'id' => $row['id'],
                'pedido_id' => $row['pedido_id'],
                'sku' => $row['sku'],
                'piezas' => $row['piezas'],
                'nombre_producto' => $row['nombre_producto'],
                'precio_unitario' => $row['precio_unitario'],
                'subtotal' => $row['subtotal'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ]);
        }
    }

    /**
     * @return array{comision_base: float, comision_iva: float, comision_total: float}
     */
    private function calcularDesgloseComision(
        float $ventaTotal,
        float $tasaComisionPct,
        float $cuotaFija,
        float $tasaIvaPct
    ): array {
        $comisionBase = round(($ventaTotal * ($tasaComisionPct / 100)) + $cuotaFija, 2);
        $comisionIva = round($comisionBase * ($tasaIvaPct / 100), 2);
        $comisionTotal = round($comisionBase + $comisionIva, 2);

        return [
            'comision_base' => $comisionBase,
            'comision_iva' => $comisionIva,
            'comision_total' => $comisionTotal,
        ];
    }

    /**
     * @return array{comision_base: float, comision_iva: float, comision_total: float}
     */
    private function desglosarComisionAlmacenada(float $comisionTotal, float $tasaIvaPct): array
    {
        $factor = 1 + ($tasaIvaPct / 100);
        $comisionBase = round($comisionTotal / $factor, 2);
        $comisionIva = round($comisionTotal - $comisionBase, 2);

        return [
            'comision_base' => $comisionBase,
            'comision_iva' => $comisionIva,
            'comision_total' => $comisionTotal,
        ];
    }
}
