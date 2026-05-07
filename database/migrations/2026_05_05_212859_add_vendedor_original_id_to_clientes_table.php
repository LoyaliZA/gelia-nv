<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ¿Por qué lo hacemos así?
     * Usamos nullable() porque los clientes nuevos o directos no tendrán un vendedor original (son propios).
     * Usamos nullOnDelete() como medida de seguridad: si un usuario (vendedor) es eliminado del sistema, 
     * no queremos que se eliminen en cascada los clientes que prospectó, solo queremos que el ID quede en nulo 
     * para mantener la integridad de los datos financieros.
     */
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('vendedor_original_id')
                  ->nullable()
                  ->after('vendedor_id')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropForeign(['vendedor_original_id']);
            $table->dropColumn('vendedor_original_id');
        });
    }
};