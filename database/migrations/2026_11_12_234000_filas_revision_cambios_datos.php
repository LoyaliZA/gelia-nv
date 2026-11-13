<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filas_revision_importacion_clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('filas_revision_importacion_clientes', 'cambios')) {
                $table->json('cambios')->nullable();
            }
            if (! Schema::hasColumn('filas_revision_importacion_clientes', 'datos')) {
                $table->json('datos')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('filas_revision_importacion_clientes', function (Blueprint $table) {
            if (Schema::hasColumn('filas_revision_importacion_clientes', 'datos')) {
                $table->dropColumn('datos');
            }
            if (Schema::hasColumn('filas_revision_importacion_clientes', 'cambios')) {
                $table->dropColumn('cambios');
            }
        });
    }
};
