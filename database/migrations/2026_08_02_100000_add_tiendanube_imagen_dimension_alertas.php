<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_producto_imagenes', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('alt');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->boolean('requiere_revision')->default(false)->after('height')->index();
            $table->boolean('alerta_pequena')->default(false)->after('requiere_revision');
            $table->boolean('alerta_no_cuadrada')->default(false)->after('alerta_pequena');
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_producto_imagenes', function (Blueprint $table) {
            $table->dropColumn([
                'width',
                'height',
                'requiere_revision',
                'alerta_pequena',
                'alerta_no_cuadrada',
            ]);
        });
    }
};
