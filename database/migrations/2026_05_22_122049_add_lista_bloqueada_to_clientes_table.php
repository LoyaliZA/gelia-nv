<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddListaBloqueadaToClientesTable extends Migration
{
    // --- CONSTANTES ---
    private const TABLA = 'clientes';
    private const COLUMNA = 'lista_bloqueada';

    // --- METODOS PRINCIPALES ---
    public function up(): void
    {
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->boolean(self::COLUMNA)
                  ->default(false)
                  ->after('es_heredado');
        });
    }

    public function down(): void
    {
        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->dropColumn(self::COLUMNA);
        });
    }
}