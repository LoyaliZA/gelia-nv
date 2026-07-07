<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_configuracion', function (Blueprint $table) {
            $table->json('mapeo_precios')->nullable()->after('notified_users');
        });

        DB::table('woocommerce_configuracion')->update([
            'mapeo_precios' => json_encode([
                'sku' => 'SKU',
                'precio_base' => 'Plataformas',
            ]),
        ]);
    }

    public function down(): void
    {
        Schema::table('woocommerce_configuracion', function (Blueprint $table) {
            $table->dropColumn('mapeo_precios');
        });
    }
};
