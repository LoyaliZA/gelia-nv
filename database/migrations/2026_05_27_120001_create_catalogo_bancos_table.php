<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_bancos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $bancos = [
            'BBVA',
            'Santander',
            'Banorte',
            'HSBC',
            'Banamex',
            'Scotiabank',
            'Inbursa',
            'Banco Azteca',
            'BanCoppel',
            'Afirme',
        ];

        foreach ($bancos as $banco) {
            DB::table('catalogo_bancos')->insert([
                'nombre' => $banco,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo_bancos');
    }
};
