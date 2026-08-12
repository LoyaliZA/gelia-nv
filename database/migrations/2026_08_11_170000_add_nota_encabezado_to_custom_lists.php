<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_lists', function (Blueprint $table) {
            $table->boolean('mostrar_nota_encabezado')->default(false)->after('filtro_relojes');
            $table->string('nota_encabezado', 500)->nullable()->after('mostrar_nota_encabezado');
        });
    }

    public function down(): void
    {
        Schema::table('custom_lists', function (Blueprint $table) {
            $table->dropColumn(['mostrar_nota_encabezado', 'nota_encabezado']);
        });
    }
};
