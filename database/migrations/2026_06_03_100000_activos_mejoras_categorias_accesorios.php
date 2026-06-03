<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_categorias_activo', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('activos', function (Blueprint $table) {
            $table->foreignId('catalogo_categoria_activo_id')
                ->nullable()
                ->after('catalogo_tipo_activo_id')
                ->constrained('catalogo_categorias_activo')
                ->nullOnDelete();
            $table->foreignId('activo_padre_id')
                ->nullable()
                ->after('catalogo_categoria_activo_id')
                ->constrained('activos')
                ->nullOnDelete();
            $table->index('activo_padre_id');
        });

        $now = now();
        $categorias = [
            ['nombre' => 'Computadora', 'slug' => 'computadora'],
            ['nombre' => 'Teléfono', 'slug' => 'telefono'],
            ['nombre' => 'Monitor', 'slug' => 'monitor'],
            ['nombre' => 'Impresora', 'slug' => 'impresora'],
            ['nombre' => 'Tablet', 'slug' => 'tablet'],
            ['nombre' => 'Otro', 'slug' => 'otro'],
        ];

        foreach ($categorias as $categoria) {
            DB::table('catalogo_categorias_activo')->insert([
                ...$categoria,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $existeAccesorio = DB::table('catalogo_tipos_activo')->where('slug', 'accesorio')->exists();
        if (!$existeAccesorio) {
            DB::table('catalogo_tipos_activo')->insert([
                'nombre' => 'Accesorio',
                'slug' => 'accesorio',
                'categoria' => 'tecnologico',
                'icono' => 'cable',
                'esquema_atributos' => json_encode([
                    'fields' => [
                        [
                            'key' => 'condicion',
                            'label' => 'Condición',
                            'type' => 'select',
                            'required' => true,
                            'options' => ['Nuevo', 'Bueno', 'Regular', 'Dañado'],
                        ],
                        [
                            'key' => 'descripcion_corta',
                            'label' => 'Descripción corta',
                            'type' => 'text',
                            'required' => false,
                            'placeholder' => 'Ej. Cargador USB-C 65W',
                        ],
                    ],
                ]),
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activo_padre_id');
            $table->dropConstrainedForeignId('catalogo_categoria_activo_id');
        });

        Schema::dropIfExists('catalogo_categorias_activo');

        DB::table('catalogo_tipos_activo')->where('slug', 'accesorio')->delete();
    }
};
