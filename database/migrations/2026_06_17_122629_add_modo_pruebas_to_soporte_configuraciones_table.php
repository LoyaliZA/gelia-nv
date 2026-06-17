<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soporte_configuraciones', function (Blueprint $table) {
            $table->boolean('modo_pruebas')->default(false)->after('hora_notificacion_diaria');
        });
    }

    public function down(): void
    {
        Schema::table('soporte_configuraciones', function (Blueprint $table) {
            $table->dropColumn('modo_pruebas');
        });
    }
};
