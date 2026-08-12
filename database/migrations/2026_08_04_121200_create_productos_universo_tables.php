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
        Schema::table('catalogo_marcas_producto', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogo_marcas_producto', 'slug')) {
                $table->string('slug', 120)->nullable()->after('nombre');
            }
        });

        if (Schema::hasColumn('catalogo_marcas_producto', 'slug')) {
            foreach (DB::table('catalogo_marcas_producto')->orderBy('id')->get() as $row) {
                $slug = Str::slug((string) $row->nombre) ?: 'marca-'.$row->id;
                DB::table('catalogo_marcas_producto')->where('id', $row->id)->update(['slug' => $slug.'-'.$row->id]);
            }
        }

        Schema::table('catalogo_categoria_productos', function (Blueprint $table) {
            if (! Schema::hasColumn('catalogo_categoria_productos', 'parent_id')) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('catalogo_categoria_productos')->nullOnDelete();
            }
            if (! Schema::hasColumn('catalogo_categoria_productos', 'slug')) {
                $table->string('slug', 120)->nullable()->after('nombre');
            }
            if (! Schema::hasColumn('catalogo_categoria_productos', 'ruta_cache')) {
                $table->string('ruta_cache', 500)->nullable()->after('slug');
            }
            if (! Schema::hasColumn('catalogo_categoria_productos', 'nivel')) {
                $table->unsignedTinyInteger('nivel')->default(0)->after('ruta_cache');
            }
            if (! Schema::hasColumn('catalogo_categoria_productos', 'estado')) {
                $table->boolean('estado')->default(true)->after('nivel');
            }
            if (! Schema::hasColumn('catalogo_categoria_productos', 'extension_perfumeria')) {
                $table->boolean('extension_perfumeria')->default(false)->after('estado');
            }
        });

        if (Schema::hasColumn('catalogo_categoria_productos', 'slug')) {
            foreach (DB::table('catalogo_categoria_productos')->orderBy('id')->get() as $row) {
                $slug = Str::slug((string) $row->nombre) ?: 'cat-'.$row->id;
                DB::table('catalogo_categoria_productos')->where('id', $row->id)->update([
                    'slug' => $slug.'-'.$row->id,
                    'ruta_cache' => $row->nombre,
                ]);
            }
        }

        Schema::create('tipos_producto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo', 40)->unique();
            $table->boolean('controla_inventario')->default(true);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('simbolo', 20);
            $table->string('dimension', 40);
            $table->decimal('factor_base', 18, 8)->nullable();
            $table->unsignedBigInteger('unidad_base_id')->nullable();
            $table->unsignedTinyInteger('decimales')->default(2);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->unique(['dimension', 'simbolo']);
        });

        Schema::create('atributos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug', 120)->unique();
            $table->string('tipo_dato', 30);
            $table->boolean('permite_multiples')->default(false);
            $table->string('dimension_unidad', 40)->nullable();
            $table->boolean('filtrable')->default(true);
            $table->boolean('buscable')->default(false);
            $table->boolean('visible_en_ficha')->default(true);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('atributo_opciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atributo_id')->constrained('atributos')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('slug', 120);
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->unique(['atributo_id', 'slug']);
        });

        Schema::create('categoria_atributos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('catalogo_categoria_productos')->cascadeOnDelete();
            $table->foreignId('atributo_id')->constrained('atributos')->cascadeOnDelete();
            $table->boolean('requerido')->default(false);
            $table->boolean('heredable')->default(true);
            $table->boolean('filtrable_override')->nullable();
            $table->boolean('visible_override')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['categoria_id', 'atributo_id']);
        });

        Schema::create('producto_atributo_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('atributo_id')->constrained('atributos')->cascadeOnDelete();
            $table->foreignId('opcion_id')->nullable()->constrained('atributo_opciones')->nullOnDelete();
            $table->text('valor_texto')->nullable();
            $table->integer('valor_entero')->nullable();
            $table->decimal('valor_decimal', 18, 6)->nullable();
            $table->boolean('valor_booleano')->nullable();
            $table->date('valor_fecha')->nullable();
            $table->foreignId('unidad_id')->nullable()->constrained('unidades_medida')->nullOnDelete();
            $table->unsignedInteger('orden')->nullable();
            $table->timestamps();
            $table->index(['producto_id', 'atributo_id']);
        });

        Schema::create('producto_relaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('producto_relacionado_id')->constrained('productos')->cascadeOnDelete();
            $table->string('tipo', 40)->default('presentacion');
            $table->unsignedInteger('orden')->nullable();
            $table->timestamps();
            $table->unique(['producto_id', 'producto_relacionado_id', 'tipo'], 'producto_relaciones_unique');
        });

        Schema::create('notas_olfativas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug', 120)->unique();
            $table->text('descripcion')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('fases_olfativas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre');
            $table->unsignedTinyInteger('orden')->default(0);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('producto_notas_olfativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('nota_olfativa_id')->constrained('notas_olfativas')->cascadeOnDelete();
            $table->foreignId('fase_olfativa_id')->constrained('fases_olfativas')->cascadeOnDelete();
            $table->unsignedInteger('orden')->default(0);
            $table->string('prominencia', 40)->nullable();
            $table->timestamps();
            $table->unique(['producto_id', 'nota_olfativa_id', 'fase_olfativa_id'], 'producto_notas_unique');
            $table->index(['producto_id', 'fase_olfativa_id', 'orden'], 'producto_notas_fase_idx');
        });

        Schema::create('canales_comerciales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo', 40)->unique();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });

        Schema::create('producto_contenidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('canal_id')->nullable()->constrained('canales_comerciales')->nullOnDelete();
            $table->string('idioma', 10)->default('es');
            $table->string('titulo_comercial')->nullable();
            $table->string('descripcion_corta', 500)->nullable();
            $table->text('descripcion_larga')->nullable();
            $table->text('pitch_venta')->nullable();
            $table->string('seo_titulo')->nullable();
            $table->string('seo_descripcion', 500)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->unique(['producto_id', 'canal_id', 'idioma'], 'producto_contenidos_unique');
        });

        Schema::create('producto_ventas_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->cascadeOnDelete();
            $table->char('periodo', 7);
            $table->decimal('cantidad_vendida', 14, 3)->nullable();
            $table->decimal('monto_venta', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['producto_id', 'almacen_id', 'periodo'], 'producto_ventas_unique');
            $table->index(['almacen_id', 'periodo']);
            $table->index(['producto_id', 'periodo']);
        });

        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'tipo_producto_id')) {
                $table->foreignId('tipo_producto_id')->nullable()->after('categoria_id')->constrained('tipos_producto')->nullOnDelete();
            }
            if (! Schema::hasColumn('productos', 'descripcion_corta')) {
                $table->string('descripcion_corta', 500)->nullable()->after('descripcion');
            }
        });

        // Seeds mínimos
        DB::table('tipos_producto')->insert([
            ['nombre' => 'Producto físico', 'codigo' => 'fisico', 'controla_inventario' => true, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Servicio', 'codigo' => 'servicio', 'controla_inventario' => false, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Kit', 'codigo' => 'kit', 'controla_inventario' => true, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $mlId = DB::table('unidades_medida')->insertGetId([
            'nombre' => 'Mililitro', 'simbolo' => 'ml', 'dimension' => 'volumen',
            'factor_base' => 1, 'unidad_base_id' => null, 'decimales' => 2, 'estado' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('unidades_medida')->insert([
            ['nombre' => 'Litro', 'simbolo' => 'L', 'dimension' => 'volumen', 'factor_base' => 1000, 'unidad_base_id' => $mlId, 'decimales' => 3, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Gramo', 'simbolo' => 'g', 'dimension' => 'masa', 'factor_base' => 1, 'unidad_base_id' => null, 'decimales' => 2, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Pieza', 'simbolo' => 'pza', 'dimension' => 'cantidad', 'factor_base' => 1, 'unidad_base_id' => null, 'decimales' => 0, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('fases_olfativas')->insert([
            ['codigo' => 'salida', 'nombre' => 'Notas de salida', 'orden' => 1, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'corazon', 'nombre' => 'Notas de corazón', 'orden' => 2, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'fondo', 'nombre' => 'Notas de fondo', 'orden' => 3, 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('canales_comerciales')->insert([
            ['nombre' => 'Interno', 'codigo' => 'interno', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Punto de venta', 'codigo' => 'punto_venta', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'GELIA', 'codigo' => 'gelia', 'estado' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->seedAtributosBase();
    }

    private function seedAtributosBase(): void
    {
        $defs = [
            ['nombre' => 'Volumen', 'slug' => 'volumen', 'tipo_dato' => 'medida', 'permite_multiples' => false, 'dimension_unidad' => 'volumen'],
            ['nombre' => 'Intensidad', 'slug' => 'intensidad', 'tipo_dato' => 'opcion', 'permite_multiples' => false, 'dimension_unidad' => null],
            ['nombre' => 'Público objetivo', 'slug' => 'publico_objetivo', 'tipo_dato' => 'opcion', 'permite_multiples' => true, 'dimension_unidad' => null],
            ['nombre' => 'Estación recomendada', 'slug' => 'estacion_recomendada', 'tipo_dato' => 'opcion', 'permite_multiples' => true, 'dimension_unidad' => null],
            ['nombre' => 'Ocasión de uso', 'slug' => 'ocasion_uso', 'tipo_dato' => 'opcion', 'permite_multiples' => true, 'dimension_unidad' => null],
            ['nombre' => 'Familia olfativa', 'slug' => 'familia_olfativa', 'tipo_dato' => 'opcion', 'permite_multiples' => true, 'dimension_unidad' => null],
            ['nombre' => 'Concentración', 'slug' => 'concentracion', 'tipo_dato' => 'opcion', 'permite_multiples' => false, 'dimension_unidad' => null],
        ];

        foreach ($defs as $def) {
            $id = DB::table('atributos')->insertGetId(array_merge($def, [
                'filtrable' => true,
                'buscable' => false,
                'visible_en_ficha' => true,
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            $opciones = match ($def['slug']) {
                'intensidad' => ['Suave', 'Medio', 'Potente'],
                'publico_objetivo' => ['Hombre', 'Mujer', 'Unisex', 'Infantil'],
                'estacion_recomendada' => ['Primavera', 'Verano', 'Otoño', 'Invierno', 'Todo el año'],
                'ocasion_uso' => ['Diario', 'Oficina', 'Noche', 'Fiesta', 'Formal'],
                'familia_olfativa' => ['Cítrica', 'Floral', 'Amaderada', 'Oriental', 'Aromática', 'Fougère', 'Fresca'],
                'concentracion' => ['Eau de Cologne', 'Eau de Toilette', 'Eau de Parfum', 'Parfum', 'Extrait de Parfum'],
                default => [],
            };

            foreach ($opciones as $i => $nombre) {
                DB::table('atributo_opciones')->insert([
                    'atributo_id' => $id,
                    'nombre' => $nombre,
                    'slug' => Str::slug($nombre),
                    'orden' => $i + 1,
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'tipo_producto_id')) {
                $table->dropConstrainedForeignId('tipo_producto_id');
            }
            if (Schema::hasColumn('productos', 'descripcion_corta')) {
                $table->dropColumn('descripcion_corta');
            }
        });

        Schema::dropIfExists('producto_ventas_almacen');
        Schema::dropIfExists('producto_contenidos');
        Schema::dropIfExists('canales_comerciales');
        Schema::dropIfExists('producto_notas_olfativas');
        Schema::dropIfExists('fases_olfativas');
        Schema::dropIfExists('notas_olfativas');
        Schema::dropIfExists('producto_relaciones');
        Schema::dropIfExists('producto_atributo_valores');
        Schema::dropIfExists('categoria_atributos');
        Schema::dropIfExists('atributo_opciones');
        Schema::dropIfExists('atributos');
        Schema::dropIfExists('unidades_medida');
        Schema::dropIfExists('tipos_producto');
    }
};
