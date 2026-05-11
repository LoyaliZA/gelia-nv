<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_usuarios', function (Blueprint $table) {
            // Agregamos una columna JSON para guardar configuraciones dinámicas del dashboard
            $table->json('dashboard_prefs')->nullable()->after('tema_visual');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_usuarios', function (Blueprint $table) {
            $table->dropColumn('dashboard_prefs');
        });
    }
};