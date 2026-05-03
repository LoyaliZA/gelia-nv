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
        Schema::create('catalogo_listas_descuento', function (Blueprint $table) {
            $table->id();
            // Nombre de la lista, ej: Bronce, Plata, Oro
            $table->string('nombre')->unique();
            // Monto minimo para alcanzar esta lista
            $table->decimal('monto_requerido', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_listas_descuento');
    }
};