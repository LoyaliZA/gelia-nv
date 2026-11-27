<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_pantalla_publicidades', function (Blueprint $table) {
            $table->unsignedSmallInteger('rotacion_kiosko')->nullable()->after('rotacion');
        });
    }

    public function down(): void
    {
        Schema::table('pdv_pantalla_publicidades', function (Blueprint $table) {
            $table->dropColumn('rotacion_kiosko');
        });
    }
};
