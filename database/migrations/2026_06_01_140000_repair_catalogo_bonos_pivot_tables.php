<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('catalogo_puesto_bonos')) {
            Schema::create('catalogo_puesto_bonos', function (Blueprint $table) {
                $table->foreignId('catalogo_puesto_id')->constrained('catalogo_puestos')->cascadeOnDelete();
                $table->foreignId('catalogo_bono_id')->constrained('catalogo_bonos')->cascadeOnDelete();
                $table->primary(['catalogo_puesto_id', 'catalogo_bono_id']);
            });
        }

        if (!Schema::hasTable('rh_colaborador_bonos')) {
            Schema::create('rh_colaborador_bonos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('rh_colaborador_id')->constrained('rh_colaboradores')->cascadeOnDelete();
                $table->foreignId('catalogo_bono_id')->constrained('catalogo_bonos')->cascadeOnDelete();
                $table->decimal('monto', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['rh_colaborador_id', 'catalogo_bono_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_colaborador_bonos');
        Schema::dropIfExists('catalogo_puesto_bonos');
    }
};
