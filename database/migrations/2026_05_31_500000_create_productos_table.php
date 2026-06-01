<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('folio')->unique();
            $table->string('sku')->unique();
            $table->string('descripcion');
            $table->integer('existencia')->default(0);
            $table->decimal('costo', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
