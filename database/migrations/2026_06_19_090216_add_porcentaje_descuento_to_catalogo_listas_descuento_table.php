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
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->decimal('porcentaje_descuento', 5, 2)->default(0)->after('monto_requerido');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->dropColumn('porcentaje_descuento');
        });
    }
};
