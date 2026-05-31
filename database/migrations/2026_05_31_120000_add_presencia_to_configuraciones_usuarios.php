<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuraciones_usuarios', function (Blueprint $table) {
            $table->json('presencia')->nullable()->after('dashboard_prefs');
        });
    }

    public function down(): void
    {
        Schema::table('configuraciones_usuarios', function (Blueprint $table) {
            $table->dropColumn('presencia');
        });
    }
};
