<?php

use App\Services\Productos\CalcularPrecioPublicoService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('productos', 'margen')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->decimal('margen', 5, 2)->nullable()->after('peso');
                $table->decimal('largo', 10, 2)->nullable()->after('margen');
                $table->decimal('ancho', 10, 2)->nullable()->after('largo');
                $table->decimal('altura', 10, 2)->nullable()->after('ancho');
                $table->decimal('precio_publico', 12, 2)->nullable()->after('altura');
            });
        }

        $this->colapsarCostos();

        if (Schema::hasColumn('producto_costos', 'almacen_id')) {
            // MySQL usa el índice único (producto_id, almacen_id) para la llave de producto_id.
            $this->dropForeignSiExiste('producto_costos', 'producto_id');
            $this->dropForeignSiExiste('producto_costos', 'almacen_id');

            Schema::table('producto_costos', function (Blueprint $table) {
                $table->dropUnique(['producto_id', 'almacen_id']);
                $table->dropColumn(['almacen_id', 'precio_venta']);
            });
        }

        $tieneUnico = collect(Schema::getIndexes('producto_costos'))
            ->contains(fn (array $indice) => ($indice['unique'] ?? false) && ($indice['columns'] ?? []) === ['producto_id']);
        if (! $tieneUnico) {
            Schema::table('producto_costos', function (Blueprint $table) {
                $table->unique('producto_id');
            });
        }

        if (! $this->tieneForeign('producto_costos', 'producto_id')) {
            Schema::table('producto_costos', function (Blueprint $table) {
                $table->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->dropForeignSiExiste('producto_costos', 'producto_id');

        Schema::table('producto_costos', function (Blueprint $table) {
            $table->dropUnique(['producto_id']);
            $table->foreignId('almacen_id')->nullable()->constrained('almacenes')->cascadeOnDelete();
            $table->decimal('precio_venta', 12, 2)->nullable();
            $table->unique(['producto_id', 'almacen_id']);
            $table->foreign('producto_id')->references('id')->on('productos')->cascadeOnDelete();
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['margen', 'largo', 'ancho', 'altura', 'precio_publico']);
        });
    }

    private function tieneForeign(string $tabla, string $columna): bool
    {
        return collect(Schema::getForeignKeys($tabla))
            ->contains(fn (array $fk) => in_array($columna, $fk['columns'] ?? [], true));
    }

    private function dropForeignSiExiste(string $tabla, string $columna): void
    {
        $fk = collect(Schema::getForeignKeys($tabla))
            ->first(fn (array $fk) => in_array($columna, $fk['columns'] ?? [], true));
        if ($fk === null) {
            return;
        }

        Schema::table($tabla, function (Blueprint $table) use ($columna) {
            $table->dropForeign([$columna]);
        });
    }

    private function colapsarCostos(): void
    {
        if (! Schema::hasColumn('producto_costos', 'almacen_id')) {
            return;
        }

        $servicio = app(CalcularPrecioPublicoService::class);
        $filas = DB::table('producto_costos as pc')
            ->join('almacenes as a', 'a.id', '=', 'pc.almacen_id')
            ->orderBy('pc.id')
            ->get([
                'pc.id',
                'pc.producto_id',
                'pc.costo',
                'pc.precio_venta',
                'a.codigo',
                'a.nombre',
            ]);

        $borrar = [];
        foreach ($filas->groupBy('producto_id') as $productoId => $grupo) {
            $elegida = $servicio->elegirFilaCosto($grupo);
            if ($elegida === null) {
                continue;
            }

            foreach ($grupo as $fila) {
                if ((int) $fila->id !== (int) $elegida->id) {
                    $borrar[] = (int) $fila->id;
                }
            }

            $margen = $servicio->margenDesdePrecio($elegida->costo, $elegida->precio_venta);
            DB::table('productos')->where('id', $productoId)->update([
                'margen' => $margen,
                'precio_publico' => $servicio->calcular($elegida->costo, $margen),
            ]);
        }

        foreach (array_chunk($borrar, 500) as $ids) {
            DB::table('producto_costos')->whereIn('id', $ids)->delete();
        }
    }
};
