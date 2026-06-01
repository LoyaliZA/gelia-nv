<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_bonos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->string('codigo')->unique()->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('catalogo_puesto_bonos', function (Blueprint $table) {
            $table->foreignId('catalogo_puesto_id')->constrained('catalogo_puestos')->cascadeOnDelete();
            $table->foreignId('catalogo_bono_id')->constrained('catalogo_bonos')->cascadeOnDelete();
            $table->primary(['catalogo_puesto_id', 'catalogo_bono_id']);
        });

        Schema::create('rh_colaborador_bonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rh_colaborador_id')->constrained('rh_colaboradores')->cascadeOnDelete();
            $table->foreignId('catalogo_bono_id')->constrained('catalogo_bonos')->cascadeOnDelete();
            $table->decimal('monto', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['rh_colaborador_id', 'catalogo_bono_id']);
        });

        Permission::findOrCreate('rh.catalogos.bonos', 'web');

        $this->seedBonos();
    }

    private function seedBonos(): void
    {
        $now = now();
        $bonos = [
            ['nombre' => 'Bono Desempaque', 'codigo' => 'bono_desempaque'],
            ['nombre' => 'Bono Caja', 'codigo' => 'bono_caja'],
            ['nombre' => 'Bono Órdenes de Compra', 'codigo' => 'bono_ordenes_compra'],
        ];

        foreach ($bonos as $bono) {
            DB::table('catalogo_bonos')->insert([
                ...$bono,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_colaborador_bonos');
        Schema::dropIfExists('catalogo_puesto_bonos');
        Schema::dropIfExists('catalogo_bonos');
    }
};
