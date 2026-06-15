<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('woocommerce_margins', function (Blueprint $table) {
            $table->id();
            $table->decimal('precio_min', 8, 2);
            $table->decimal('precio_max', 10, 2);
            $table->decimal('multiplicador_rebaja', 5, 2); // Ej: 1.70
            $table->decimal('multiplicador_normal', 5, 2); // Ej: 1.80
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('woocommerce_margins');
    }
};
