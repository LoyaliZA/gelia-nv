<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->cascadeOnDelete();
            $table->string('ubicacion', 50)->nullable();
            $table->decimal('existencia', 12, 3)->default(0);
            $table->decimal('apartado', 12, 3)->default(0);
            $table->decimal('transito_oc', 12, 3)->default(0);
            $table->decimal('transito_ot', 12, 3)->default(0);
            $table->decimal('minimo', 12, 3)->nullable();
            $table->decimal('maximo', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['producto_id', 'almacen_id']);
        });

        Schema::create('producto_costos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('almacen_id')->constrained('almacenes')->cascadeOnDelete();
            $table->decimal('costo', 12, 2)->default(0);
            $table->decimal('costo_reposicion', 12, 2)->nullable();
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['producto_id', 'almacen_id']);
        });

        $productos = DB::table('productos')->orderBy('id')->get();
        $folioNumerico = 100000;

        foreach ($productos as $producto) {
            if ($producto->almacen_id) {
                DB::table('inventarios')->insert([
                    'producto_id' => $producto->id,
                    'almacen_id' => $producto->almacen_id,
                    'existencia' => $producto->existencia ?? 0,
                    'apartado' => 0,
                    'transito_oc' => 0,
                    'transito_ot' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('producto_costos')->insert([
                    'producto_id' => $producto->id,
                    'almacen_id' => $producto->almacen_id,
                    'costo' => $producto->costo ?? 0,
                    'costo_reposicion' => null,
                    'precio_venta' => $producto->precio_venta,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $folioNumerico++;
            DB::table('productos')->where('id', $producto->id)->update([
                'folio' => (string) $folioNumerico,
            ]);
        }

        if (! DB::table('sucursales')->exists()) {
            $sucursalId = DB::table('sucursales')->insertGetId([
                'codigo' => 'PRINCIPAL',
                'nombre' => 'Principal',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $sucursalId = DB::table('sucursales')->value('id');
        }

        if (! DB::table('catalogo_tipos_almacen')->exists()) {
            $tipoId = DB::table('catalogo_tipos_almacen')->insertGetId([
                'nombre' => 'General',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $tipoId = DB::table('catalogo_tipos_almacen')->value('id');
        }

        DB::table('almacenes')->whereNull('sucursal_id')->update([
            'sucursal_id' => $sucursalId,
            'tipo_almacen_id' => $tipoId,
        ]);

        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['almacen_id']);
            $table->dropColumn(['almacen_id', 'existencia', 'costo', 'precio_venta']);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('marca_id')->nullable()->after('categoria_id')->constrained('catalogo_marcas_producto')->nullOnDelete();
            $table->string('codigo_barras', 30)->nullable()->after('marca_id');
            $table->decimal('peso', 10, 3)->nullable()->after('codigo_barras');
            $table->string('imagen_path')->nullable()->after('peso');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE productos MODIFY folio INT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE productos ALTER COLUMN folio TYPE INTEGER USING folio::integer');
        }
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->integer('existencia')->default(0);
            $table->decimal('costo', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->foreignId('almacen_id')->nullable()->constrained('almacenes')->nullOnDelete();
        });

        $inventarios = DB::table('inventarios')->get();
        foreach ($inventarios as $inv) {
            $costo = DB::table('producto_costos')
                ->where('producto_id', $inv->producto_id)
                ->where('almacen_id', $inv->almacen_id)
                ->first();

            DB::table('productos')->where('id', $inv->producto_id)->update([
                'almacen_id' => $inv->almacen_id,
                'existencia' => $inv->existencia,
                'costo' => $costo->costo ?? 0,
                'precio_venta' => $costo->precio_venta ?? null,
            ]);
        }

        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['marca_id']);
            $table->dropColumn(['marca_id', 'codigo_barras', 'peso', 'imagen_path']);
        });

        Schema::dropIfExists('producto_costos');
        Schema::dropIfExists('inventarios');
    }
};
