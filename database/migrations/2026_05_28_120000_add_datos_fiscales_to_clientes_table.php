<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('rfc')->nullable()->after('nombre');
            $table->string('codigo_postal', 10)->nullable()->after('rfc');
            $table->string('regimen_fiscal')->nullable()->after('codigo_postal');
            $table->string('correo_electronico')->nullable()->after('regimen_fiscal');
            $table->string('uso_factura')->nullable()->after('correo_electronico');
            $table->string('nombre_razon_social')->nullable()->after('uso_factura');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'rfc',
                'codigo_postal',
                'regimen_fiscal',
                'correo_electronico',
                'uso_factura',
                'nombre_razon_social',
            ]);
        });
    }
};
