<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('documentos_venta', 'datos_fuente')) {
            Schema::table('documentos_venta', function (Blueprint $table) {
                $table->json('datos_fuente')->nullable()->after('remision_original');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('documentos_venta', 'datos_fuente')) {
            Schema::table('documentos_venta', function (Blueprint $table) {
                $table->dropColumn('datos_fuente');
            });
        }
    }
};
