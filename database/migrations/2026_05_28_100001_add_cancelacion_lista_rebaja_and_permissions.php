<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->foreignId('catalogo_lista_rebaja_id')
                ->nullable()
                ->after('motivo_cancelacion')
                ->constrained('catalogo_listas_descuento')
                ->nullOnDelete();
        });

        Permission::findOrCreate('solicitudes.solicitar_cancelacion');
        Permission::findOrCreate('solicitudes.confirmar_cambio_lista');
    }

    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropForeign(['catalogo_lista_rebaja_id']);
            $table->dropColumn('catalogo_lista_rebaja_id');
        });

        Permission::whereIn('name', [
            'solicitudes.solicitar_cancelacion',
            'solicitudes.confirmar_cambio_lista',
        ])->delete();
    }
};
