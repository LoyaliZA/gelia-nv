<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_tipo_clientes', function (Blueprint $Blueprint) {
            $Blueprint->id();
            $Blueprint->string('nombre'); // NUEVO, REACTIVADO, ACTIVO, etc.
            $Blueprint->boolean('activo')->default(true);
            $Blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_tipo_clientes');
    }
};