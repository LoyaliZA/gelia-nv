<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_pantalla_publicidades', function (Blueprint $table) {
            $table->boolean('eliminar_automaticamente')->default(false)->after('vigente_hasta');
            $table->timestamp('eliminar_programado_at')->nullable()->after('eliminar_automaticamente');
            $table->index('eliminar_programado_at', 'pdv_pantalla_pub_depuracion_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pdv_pantalla_publicidades', function (Blueprint $table) {
            $table->dropIndex('pdv_pantalla_pub_depuracion_idx');
            $table->dropColumn(['eliminar_automaticamente', 'eliminar_programado_at']);
        });
    }
};
