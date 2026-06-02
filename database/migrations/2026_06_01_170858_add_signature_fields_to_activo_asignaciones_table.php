<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activo_asignaciones', function (Blueprint $table) {
            $table->boolean('firmado')->default(false)->after('notas');
            $table->string('firma_ruta')->nullable()->after('firmado');
            $table->timestamp('firma_fecha')->nullable()->after('firma_ruta');
            $table->text('condiciones_entrega')->nullable()->after('firma_fecha');
            $table->text('condiciones_devolucion')->nullable()->after('condiciones_entrega');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activo_asignaciones', function (Blueprint $table) {
            $table->dropColumn([
                'firmado',
                'firma_ruta',
                'firma_fecha',
                'condiciones_entrega',
                'condiciones_devolucion',
            ]);
        });
    }
};
