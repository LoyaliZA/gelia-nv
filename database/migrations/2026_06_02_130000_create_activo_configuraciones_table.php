<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activo_configuraciones', function (Blueprint $table) {
            $table->id();
            $table->text('terminos_condiciones');
            $table->timestamps();
        });

        // Insertar registro inicial por defecto
        DB::table('activo_configuraciones')->insert([
            'terminos_condiciones' => "1. Recepción e Inventario: El colaborador declara recibir a su entera satisfacción el activo descrito anteriormente en las condiciones de entrega detalladas en este documento.\n2. Uso y Resguardo: El colaborador se obliga a destinar el activo única y exclusivamente para el desempeño de sus funciones laborales dentro de la empresa, comprometiéndose a mantenerlo bajo su resguardo, cuidado y en condiciones óptimas de operación.\n3. Responsabilidad por Daños: El colaborador se compromete a regresar los activos en las mismas condiciones en las que le fueron entregados, salvo por el desgaste natural derivado de su uso adecuado. En caso de presentar daños parciales o totales causados por negligencia, descuido o mal uso, el colaborador acepta la responsabilidad de cubrir la totalidad del costo de mantenimiento, reparación o reposición del activo.\n4. Devolución: El colaborador se compromete a devolver el activo de inmediato al serle requerido por el departamento correspondiente o al momento del término de la relación laboral.",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activo_configuraciones');
    }
};
