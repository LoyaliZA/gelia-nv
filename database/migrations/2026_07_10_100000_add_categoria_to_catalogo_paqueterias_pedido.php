<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_paqueterias_pedido', function (Blueprint $table) {
            $table->string('categoria', 30)->default('local_regional')->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_paqueterias_pedido', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });
    }
};
