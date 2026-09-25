<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_configuracion', function (Blueprint $table) {
            $table->text('integration_token')->nullable()->after('consumer_secret');
        });
    }

    public function down(): void
    {
        Schema::table('woocommerce_configuracion', function (Blueprint $table) {
            $table->dropColumn('integration_token');
        });
    }
};
