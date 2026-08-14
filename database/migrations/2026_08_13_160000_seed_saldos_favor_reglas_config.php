<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @return list<array{clave: string, valor: string, tipo: string, grupo: string, descripcion: string}> */
    private function filas(): array
    {
        return [
            [
                'clave' => 'saldos_favor.monto_minimo',
                'valor' => '10',
                'tipo' => 'decimal',
                'grupo' => 'saldos_favor',
                'descripcion' => 'Monto mínimo para generar saldo a favor',
            ],
            [
                'clave' => 'saldos_favor.vigencia_modo',
                'valor' => 'dias',
                'tipo' => 'string',
                'grupo' => 'saldos_favor',
                'descripcion' => 'Modo de vigencia: dias o fecha_limite',
            ],
            [
                'clave' => 'saldos_favor.vigencia_dias',
                'valor' => '20',
                'tipo' => 'integer',
                'grupo' => 'saldos_favor',
                'descripcion' => 'Días de vigencia desde la generación',
            ],
            [
                'clave' => 'saldos_favor.fecha_limite',
                'valor' => '',
                'tipo' => 'string',
                'grupo' => 'saldos_favor',
                'descripcion' => 'Fecha límite de vigencia (modo fecha_limite)',
            ],
        ];
    }

    public function up(): void
    {
        if (! Schema::hasTable('configuraciones_sistema')) {
            return;
        }

        $now = now();
        foreach ($this->filas() as $fila) {
            if (DB::table('configuraciones_sistema')->where('clave', $fila['clave'])->exists()) {
                continue;
            }
            DB::table('configuraciones_sistema')->insert([
                ...$fila,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('configuraciones_sistema_globales');
        Cache::forget('saldos_favor.reglas');
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuraciones_sistema')) {
            return;
        }

        DB::table('configuraciones_sistema')
            ->whereIn('clave', array_column($this->filas(), 'clave'))
            ->delete();

        Cache::forget('configuraciones_sistema_globales');
        Cache::forget('saldos_favor.reglas');
    }
};
