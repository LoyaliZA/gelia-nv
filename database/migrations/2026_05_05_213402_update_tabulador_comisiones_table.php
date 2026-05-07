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
    Schema::table('tabulador_comisiones', function (Blueprint $table) {
        // Renombramos o agregamos para diferenciar
        $table->decimal('monto_vendedora', 10, 2)->default(0)->after('monto_comision');
        $table->decimal('monto_original', 10, 2)->default(0)->after('monto_vendedora');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
