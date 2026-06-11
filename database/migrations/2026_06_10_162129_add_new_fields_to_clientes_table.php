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
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('direccion_fiscal')->nullable();
            $table->string('colonia_fiscal')->nullable();
            $table->string('municipio_fiscal')->nullable();
            $table->string('estado_fiscal')->nullable();
            $table->string('pais_fiscal')->nullable();
            $table->string('direccion_contacto')->nullable();
            $table->string('colonia_contacto')->nullable();
            $table->string('municipio_contacto')->nullable();
            $table->string('estado_contacto')->nullable();
            $table->string('pais_contacto')->nullable();
            $table->string('cp_contacto', 10)->nullable();
            $table->integer('dias_cheque_postfechado')->nullable();
            $table->string('parte_relacional')->nullable();
            $table->string('variable_contable')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'direccion_fiscal',
                'colonia_fiscal',
                'municipio_fiscal',
                'estado_fiscal',
                'pais_fiscal',
                'direccion_contacto',
                'colonia_contacto',
                'municipio_contacto',
                'estado_contacto',
                'pais_contacto',
                'cp_contacto',
                'dias_cheque_postfechado',
                'parte_relacional',
                'variable_contable',
            ]);
        });
    }
};
