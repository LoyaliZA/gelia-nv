<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('woocommerce_products', function (Blueprint $table) {
            $table->decimal('precio_normal', 10, 2)->nullable()->after('nombre');
            $table->decimal('precio_rebajado', 10, 2)->nullable()->after('precio_normal');
        });
    }

    public function down()
    {
        Schema::table('woocommerce_products', function (Blueprint $table) {
            $table->dropColumn(['precio_normal', 'precio_rebajado']);
        });
    }
};
