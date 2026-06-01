<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mensaje_adjuntos') || Schema::hasColumn('mensaje_adjuntos', 'contenido_indexado')) {
            return;
        }

        Schema::table('mensaje_adjuntos', function (Blueprint $table) {
            $table->text('contenido_indexado')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mensaje_adjuntos') || !Schema::hasColumn('mensaje_adjuntos', 'contenido_indexado')) {
            return;
        }

        Schema::table('mensaje_adjuntos', function (Blueprint $table) {
            $table->dropColumn('contenido_indexado');
        });
    }
};
