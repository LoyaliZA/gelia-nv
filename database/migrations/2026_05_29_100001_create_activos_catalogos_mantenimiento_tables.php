<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_marcas_activo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_tipo_activo_id')->constrained('catalogo_tipos_activo')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('slug');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['catalogo_tipo_activo_id', 'slug']);
        });

        Schema::create('catalogo_modelos_activo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_marca_activo_id')->constrained('catalogo_marcas_activo')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('slug');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['catalogo_marca_activo_id', 'slug']);
        });

        Schema::create('activo_mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activo_id')->constrained('activos')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->enum('tipo', ['preventivo', 'correctivo', 'garantia'])->default('preventivo');
            $table->enum('estado', ['programado', 'en_proceso', 'completado', 'cancelado'])->default('programado');
            $table->date('fecha_programada')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->string('proveedor')->nullable();
            $table->decimal('costo', 12, 2)->nullable();
            $table->text('descripcion')->nullable();
            $table->text('notas')->nullable();
            $table->date('proximo_mantenimiento')->nullable();
            $table->timestamps();

            $table->index(['activo_id', 'estado']);
            $table->index('fecha_programada');
        });

        $this->seedMarcasModelos();
        $this->updateEsquemasTipos();
    }

    private function seedMarcasModelos(): void
    {
        $tipos = DB::table('catalogo_tipos_activo')->pluck('id', 'slug');

        $catalogo = [
            'equipo-ti' => [
                'marcas' => [
                    'Dell' => ['Latitude 5420', 'OptiPlex 7090'],
                    'HP' => ['EliteBook 840', 'ProDesk 400'],
                    'Lenovo' => ['ThinkPad T14', 'ThinkCentre M70q'],
                ],
            ],
            'mueble' => [
                'marcas' => [
                    'IKEA' => ['MARKUS', 'ALEX'],
                    'Office Depot' => ['Silla Ejecutiva', 'Escritorio L'],
                ],
            ],
            'uniforme' => [
                'marcas' => [
                    'Dickies' => ['Camisa Manga Larga', 'Pantalón Cargo'],
                    'Cintas' => ['Playera Polo', 'Chaleco'],
                ],
            ],
            'herramienta-mantenimiento' => [
                'marcas' => [
                    'Truper' => ['Taladro Inalámbrico', 'Juego de Desarmadores'],
                    'Stanley' => ['Martillo', 'Cinta Métrica'],
                ],
            ],
        ];

        foreach ($catalogo as $slug => $data) {
            $tipoId = $tipos[$slug] ?? null;
            if (!$tipoId) continue;

            foreach ($data['marcas'] as $marcaNombre => $modelos) {
                $marcaId = DB::table('catalogo_marcas_activo')->insertGetId([
                    'catalogo_tipo_activo_id' => $tipoId,
                    'nombre' => $marcaNombre,
                    'slug' => Str::slug($marcaNombre),
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($modelos as $modeloNombre) {
                    DB::table('catalogo_modelos_activo')->insert([
                        'catalogo_marca_activo_id' => $marcaId,
                        'nombre' => $modeloNombre,
                        'slug' => Str::slug($modeloNombre),
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function updateEsquemasTipos(): void
    {
        $updates = [
            'equipo-ti' => ['marca' => 'catalog_marca', 'modelo' => 'catalog_modelo'],
            'mueble' => ['marca' => 'catalog_marca'],
            'herramienta-mantenimiento' => ['marca' => 'catalog_marca', 'modelo' => 'catalog_modelo'],
        ];

        foreach ($updates as $slug => $fieldTypes) {
            $tipo = DB::table('catalogo_tipos_activo')->where('slug', $slug)->first();
            if (!$tipo) continue;

            $esquema = json_decode($tipo->esquema_atributos, true) ?? ['fields' => []];
            foreach ($esquema['fields'] as &$field) {
                if (isset($fieldTypes[$field['key']])) {
                    $field['type'] = $fieldTypes[$field['key']];
                }
            }
            unset($field);

            DB::table('catalogo_tipos_activo')->where('id', $tipo->id)->update([
                'esquema_atributos' => json_encode($esquema),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activo_mantenimientos');
        Schema::dropIfExists('catalogo_modelos_activo');
        Schema::dropIfExists('catalogo_marcas_activo');
    }
};
