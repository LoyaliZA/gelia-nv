<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('woocommerce_sync_logs', function (Blueprint $table) {
            $table->string('tipo')->default('upload_prices')->after('id');
            $table->json('payload')->nullable()->after('mensaje_error');
        });
    }

    public function down(): void
    {
        Schema::table('woocommerce_sync_logs', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'payload']);
        });
    }
};
