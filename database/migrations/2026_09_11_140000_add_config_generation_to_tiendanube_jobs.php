<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_webhook_deliveries', function (Blueprint $table) {
            $table->unsignedInteger('config_generation')->nullable()->after('store_id');
        });

        Schema::table('tiendanube_image_imports', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('user_id');
            $table->unsignedInteger('config_generation')->nullable()->after('store_id');
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_webhook_deliveries', function (Blueprint $table) {
            $table->dropColumn('config_generation');
        });

        Schema::table('tiendanube_image_imports', function (Blueprint $table) {
            $table->dropColumn(['store_id', 'config_generation']);
        });
    }
};
