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
        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->constrained('almacenes')->onDelete('set null');
            $table->foreignId('categoria_id')->nullable()->constrained('catalogo_categoria_productos')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['almacen_id']);
            $table->dropForeign(['categoria_id']);
            $table->dropColumn(['almacen_id', 'categoria_id']);
        });
    }
};
