<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendanube_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('event', 150)->nullable()->index();
            $table->string('resource_id', 64)->nullable();
            $table->json('payload')->nullable();
            $table->string('payload_hash', 64)->unique();
            $table->boolean('hmac_valid')->default(true);
            $table->string('status', 20)->default('received')->index();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_webhook_deliveries');
    }
};
