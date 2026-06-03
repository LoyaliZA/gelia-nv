<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('es_inactivo')->default(false)->after('es_heredado');
            $table->index('es_inactivo', 'clientes_es_inactivo_index');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('clientes_es_inactivo_index');
            $table->dropColumn('es_inactivo');
        });
    }
};
