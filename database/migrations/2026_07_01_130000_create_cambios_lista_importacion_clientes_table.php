<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cambios_lista_importacion_clientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('importacion_cliente_id');
            $table->foreign('importacion_cliente_id', 'imp_cli_cambios_lista_fk')
                ->references('id')
                ->on('importaciones_clientes')
                ->cascadeOnDelete();
            $table->string('numero_cliente');
            $table->string('nombre_cliente')->nullable();
            $table->string('lista_anterior');
            $table->string('lista_nueva');
            $table->string('tipo_cambio', 20);
            $table->string('codigo_lista')->nullable();
            $table->decimal('monto_nuevo', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambios_lista_importacion_clientes');
    }
};
