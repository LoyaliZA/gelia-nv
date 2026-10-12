<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('almacenes', function (Blueprint $table) {
            $table->boolean('permite_salida_pedidos')
                ->default(true)
                ->after('permite_busqueda_productos');
        });

        DB::table('almacenes')
            ->where(function ($query) {
                $query->whereRaw('LOWER(nombre) LIKE ?', ['%city center%'])
                    ->orWhereRaw('LOWER(codigo) LIKE ?', ['%city center%'])
                    ->orWhereRaw("REPLACE(LOWER(nombre), ' ', '') LIKE ?", ['%citycenter%'])
                    ->orWhereRaw("REPLACE(LOWER(codigo), ' ', '') LIKE ?", ['%citycenter%']);
            })
            ->update(['permite_salida_pedidos' => false]);
    }

    public function down(): void
    {
        Schema::table('almacenes', function (Blueprint $table) {
            $table->dropColumn('permite_salida_pedidos');
        });
    }
};
