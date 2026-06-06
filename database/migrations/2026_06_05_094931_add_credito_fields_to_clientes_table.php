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
        Schema::table('clientes', function (Blueprint $table) {
            $table->decimal('monto_credito_autorizado', 15, 2)->nullable()->after('lista_bloqueada');
            $table->integer('dias_credito')->nullable()->after('monto_credito_autorizado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['monto_credito_autorizado', 'dias_credito']);
        });
    }
};
