<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_entregas', function (Blueprint $table) {
            // Se agrega como texto para soportar la longitud de la cadena cifrada de Laravel
            $table->text('api_key_google')->nullable()->after('usar_api_distancia');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_entregas', function (Blueprint $table) {
            $table->dropColumn('api_key_google');
        });
    }
};