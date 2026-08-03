<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_sync_logs', function (Blueprint $table) {
            $table->unsignedInteger('eliminados_productos')->default(0)->after('procesados_productos');
            $table->unsignedInteger('eliminados_categorias')->default(0)->after('eliminados_productos');
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_sync_logs', function (Blueprint $table) {
            $table->dropColumn(['eliminados_productos', 'eliminados_categorias']);
        });
    }
};
