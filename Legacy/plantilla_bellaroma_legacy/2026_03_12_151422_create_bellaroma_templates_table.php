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
        Schema::create('bellaroma_templates', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo'); // Ej: PLANTILLA-BELLAROMA-12-03-26_083015.xlsx
            $table->string('ruta_fisica'); // Ruta en el storage
            $table->string('tamano_kb')->nullable();
            $table->boolean('enviado_correo')->default(false);
            $table->boolean('subido_drive')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bellaroma_templates');
    }
};
