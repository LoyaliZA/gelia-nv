<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogo_bancos', fn (Blueprint $table) => $table->boolean('compartido')->default(true));
        Schema::table('pedidos_bma', fn (Blueprint $table) => $table->foreignId('departamento_operacion_id')->nullable()->constrained('departamentos')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', fn (Blueprint $table) => $table->dropConstrainedForeignId('departamento_operacion_id'));
        Schema::table('catalogo_bancos', fn (Blueprint $table) => $table->dropColumn('compartido'));
    }
};
