<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones_clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('importaciones_clientes', 'cursor_fila')) {
                $table->unsignedInteger('cursor_fila')->default(0);
            }
            if (! Schema::hasColumn('importaciones_clientes', 'contadores')) {
                $table->json('contadores')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('importaciones_clientes', function (Blueprint $table) {
            if (Schema::hasColumn('importaciones_clientes', 'contadores')) {
                $table->dropColumn('contadores');
            }
            if (Schema::hasColumn('importaciones_clientes', 'cursor_fila')) {
                $table->dropColumn('cursor_fila');
            }
        });
    }
};
