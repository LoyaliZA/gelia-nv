<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_tipos_faltas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->decimal('factor_penalizacion_puntualidad', 8, 2)->default(0);
            $table->decimal('factor_penalizacion_productividad', 8, 2)->default(0);
            $table->boolean('aplica_deduccion_salario_base')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Permission::findOrCreate('rh.catalogos.tipos_faltas', 'web');

        $this->seedTiposFaltas();
    }

    private function seedTiposFaltas(): void
    {
        $now = now();
        $registros = [
            ['nombre' => 'Falta Injustificada', 'factor_penalizacion_puntualidad' => 15.00, 'factor_penalizacion_productividad' => 15.00, 'aplica_deduccion_salario_base' => true],
            ['nombre' => 'Falta Justificada', 'factor_penalizacion_puntualidad' => 0.00, 'factor_penalizacion_productividad' => 0.00, 'aplica_deduccion_salario_base' => false],
            ['nombre' => 'Retardo Menor (1 a 10 min)', 'factor_penalizacion_puntualidad' => 0.50, 'factor_penalizacion_productividad' => 0.00, 'aplica_deduccion_salario_base' => false],
            ['nombre' => 'Retardo Mayor (más de 10 min)', 'factor_penalizacion_puntualidad' => 1.00, 'factor_penalizacion_productividad' => 0.00, 'aplica_deduccion_salario_base' => false],
            ['nombre' => 'Permiso con Goce de Sueldo', 'factor_penalizacion_puntualidad' => 0.00, 'factor_penalizacion_productividad' => 0.00, 'aplica_deduccion_salario_base' => false],
            ['nombre' => 'Permiso sin Goce de Sueldo', 'factor_penalizacion_puntualidad' => 0.00, 'factor_penalizacion_productividad' => 0.00, 'aplica_deduccion_salario_base' => true],
        ];

        foreach ($registros as $registro) {
            DB::table('catalogo_tipos_faltas')->insert([
                ...$registro,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_tipos_faltas');
    }
};
