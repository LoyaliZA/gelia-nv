<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_precio_ejecuciones', function (Blueprint $table) {
            $table->string('continuacion_programada_en', 32)->nullable()->after('lease_expires_at');
            $table->string('continuacion_despachada_en', 32)->nullable()->after('continuacion_programada_en');
            $table->string('continuacion_despacho_token', 64)->nullable()->after('continuacion_despachada_en');
            $table->timestamp('continuacion_despacho_expira_at')->nullable()->after('continuacion_despacho_token');
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_precio_ejecuciones', function (Blueprint $table) {
            $table->dropColumn([
                'continuacion_programada_en',
                'continuacion_despachada_en',
                'continuacion_despacho_token',
                'continuacion_despacho_expira_at',
            ]);
        });
    }
};
