<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $folioNum = DB::getDriverName() !== 'sqlite' 
            ? (int) DB::table('catalogo_reglas_incidencia')->max(DB::raw("CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)"))
            : 0;

        if (DB::getDriverName() === 'sqlite' && $maxFolio = DB::table('catalogo_reglas_incidencia')->max('folio')) {
            $parts = explode('-', $maxFolio);
            $folioNum = (int) end($parts);
        }

        $reglas = [
            [
                'nombre' => 'INCIDENCIA GENERAL GERENTES',
                'categoria' => 'operativa',
                'tipo_comportamiento' => 'cobro_fijo',
                'monto_fijo' => 200.00,
                'factor_penalizacion_puntualidad' => 0,
                'factor_penalizacion_productividad' => 0,
            ],
            [
                'nombre' => 'RELOJ CHECADOR',
                'categoria' => 'operativa',
                'tipo_comportamiento' => 'cobro_fijo',
                'monto_fijo' => 40.00,
                'factor_penalizacion_puntualidad' => 0,
                'factor_penalizacion_productividad' => 0,
            ],
            [
                'nombre' => 'MOVIMIENTO ERRONEO EN WIZERP (FONDO PARA INVENTARIO)',
                'categoria' => 'operativa',
                'tipo_comportamiento' => 'cobro_fijo',
                'monto_fijo' => 250.00,
                'factor_penalizacion_puntualidad' => 0,
                'factor_penalizacion_productividad' => 0,
            ],
            [
                'nombre' => 'CANCELACIÓN DE REMISIÓN',
                'categoria' => 'operativa',
                'tipo_comportamiento' => 'cobro_fijo',
                'monto_fijo' => 150.00,
                'factor_penalizacion_puntualidad' => 0,
                'factor_penalizacion_productividad' => 0,
            ],
            [
                'nombre' => 'PERFUME ROTO',
                'categoria' => 'operativa',
                'tipo_comportamiento' => 'cobro_costo_producto',
                'monto_fijo' => 0,
                'factor_penalizacion_puntualidad' => 0,
                'factor_penalizacion_productividad' => 0,
            ],
            [
                'nombre' => 'PERFUME DAÑADO SI APLICA PARA REMATE',
                'categoria' => 'operativa',
                'tipo_comportamiento' => 'cobro_costo_producto',
                'monto_fijo' => 0,
                'factor_penalizacion_puntualidad' => 0,
                'factor_penalizacion_productividad' => 0,
            ],
            [
                'nombre' => 'INCIDENCIA DE PROCESOS QUE DEN ALGUN BONO O COMISIÓN (PIERDES BONO)',
                'categoria' => 'operativa',
                'tipo_comportamiento' => 'cancelacion_bono_especifico',
                'monto_fijo' => 0,
                'factor_penalizacion_puntualidad' => 0,
                'factor_penalizacion_productividad' => 0,
            ],
            [
                'nombre' => 'INSUBORDINACIÓN (PIERDE EL BONO DE PRODUCTIVIDAD COMPLETO DE LA QUINCENA)',
                'categoria' => 'falta',
                'tipo_comportamiento' => 'deduccion_nomina',
                'monto_fijo' => 0,
                'factor_penalizacion_puntualidad' => 0,
                'factor_penalizacion_productividad' => 1.0,
            ]
        ];

        foreach ($reglas as $regla) {
            $folioNum++;
            DB::table('catalogo_reglas_incidencia')->insert(array_merge($regla, [
                'uuid' => (string) Str::uuid(),
                'folio' => 'REG-' . str_pad((string) $folioNum, 6, '0', STR_PAD_LEFT),
                'aplica_deduccion_salario_base' => false,
                'recompensa_auditor_activa' => false,
                'monto_recompensa_auditor' => 0,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        // No se elimina porque las reglas pueden estar ligadas a deducciones.
        // Se tendrían que desactivar en todo caso.
    }
};
