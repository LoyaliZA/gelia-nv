<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pdv_pantalla_publicidades')) {
            return;
        }

        Schema::table('pdv_pantalla_publicidades', function (Blueprint $table) {
            $table->unsignedSmallInteger('rotacion')->default(0)->change();
            $table->unsignedSmallInteger('rotacion_kiosko')->nullable()->change();
        });
    }

    public function down(): void
    {
        // No revertir a TINYINT: 270° queda fuera de rango (0-255).
    }
};
