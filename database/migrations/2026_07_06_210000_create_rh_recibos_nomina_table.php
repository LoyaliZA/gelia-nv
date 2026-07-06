<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_recibos_nomina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rh_colaborador_id')->constrained('rh_colaboradores')->cascadeOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('firma_colaborador_path');
            $table->foreignId('firmado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('firmado_en');
            $table->timestamps();

            $table->unique(
                ['rh_colaborador_id', 'fecha_inicio', 'fecha_fin'],
                'rh_recibo_nomina_periodo_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_recibos_nomina');
    }
};
