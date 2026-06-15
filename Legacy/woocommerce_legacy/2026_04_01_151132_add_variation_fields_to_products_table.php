<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('woocommerce_products', function (Blueprint $table) {
            // 'tipo' para saber si es simple/variation y 'parent_id' para la ruta de la API
            $table->string('tipo')->default('simple')->after('nombre');
            $table->unsignedBigInteger('parent_id')->nullable()->after('tipo');
        });
    }

    public function down()
    {
        Schema::table('woocommerce_products', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'parent_id']);
        });
    }
};