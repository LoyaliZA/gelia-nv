<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('solicitud_importes_provisionales') && ! Schema::hasColumn('solicitud_importes_provisionales', 'fecha_confirmacion')) {
            Schema::table('solicitud_importes_provisionales', function (Blueprint $table) {
                $table->dateTime('fecha_confirmacion')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('solicitud_importes_provisionales') && Schema::hasColumn('solicitud_importes_provisionales', 'fecha_confirmacion')) {
            Schema::table('solicitud_importes_provisionales', function (Blueprint $table) {
                $table->dropColumn('fecha_confirmacion');
            });
        }
    }
};
