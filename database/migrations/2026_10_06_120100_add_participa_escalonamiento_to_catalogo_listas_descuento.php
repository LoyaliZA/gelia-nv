<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->boolean('participa_escalonamiento')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('catalogo_listas_descuento', function (Blueprint $table) {
            $table->dropColumn('participa_escalonamiento');
        });
    }
};
