<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_productos', function (Blueprint $table) {
            $table->boolean('free_shipping')->default(false)->after('published');
            $table->boolean('requires_shipping')->default(true)->after('free_shipping');
            $table->string('video_url', 2048)->nullable()->after('requires_shipping');
        });

        Schema::table('tiendanube_producto_variantes', function (Blueprint $table) {
            $table->decimal('cost', 12, 2)->nullable()->after('promotional_price');
        });

        Schema::table('tiendanube_producto_imagenes', function (Blueprint $table) {
            $table->string('src', 2048)->nullable()->change();
            $table->string('alt', 512)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_productos', function (Blueprint $table) {
            $table->dropColumn(['free_shipping', 'requires_shipping', 'video_url']);
        });

        Schema::table('tiendanube_producto_variantes', function (Blueprint $table) {
            $table->dropColumn('cost');
        });

        Schema::table('tiendanube_producto_imagenes', function (Blueprint $table) {
            $table->string('src')->nullable()->change();
            $table->string('alt')->nullable()->change();
        });
    }
};
