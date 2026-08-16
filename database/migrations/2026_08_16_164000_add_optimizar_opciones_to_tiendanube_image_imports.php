<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_image_imports', function (Blueprint $table) {
            $table->boolean('convertir_webp')->default(true)->after('reemplazar_primera');
            $table->string('modo_1280', 16)->default('none')->after('convertir_webp');
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_image_imports', function (Blueprint $table) {
            $table->dropColumn(['convertir_webp', 'modo_1280']);
        });
    }
};
