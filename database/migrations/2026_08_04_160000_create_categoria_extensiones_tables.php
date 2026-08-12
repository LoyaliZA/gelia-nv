<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('extensiones_producto')) {
            Schema::create('extensiones_producto', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 40)->unique();
                $table->string('nombre');
                $table->string('descripcion')->nullable();
                $table->string('version', 20)->nullable();
                $table->boolean('habilitada')->default(true);
                $table->json('configuracion_json')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('categoria_extensiones')) {
            Schema::create('categoria_extensiones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('categoria_id')->constrained('catalogo_categoria_productos')->cascadeOnDelete();
                $table->foreignId('extension_id')->constrained('extensiones_producto')->restrictOnDelete();
                $table->boolean('habilitada')->default(true);
                $table->boolean('heredable')->default(true);
                $table->json('configuracion_json')->nullable();
                $table->timestamps();

                $table->unique(['categoria_id', 'extension_id']);
                $table->index(['extension_id', 'habilitada']);
                $table->index(['categoria_id', 'habilitada']);
            });
        }

        $extensionId = $this->seedPerfumeria();
        $this->backfillAsignaciones($extensionId);

        if (Schema::hasColumn('catalogo_categoria_productos', 'extension_perfumeria')) {
            Schema::table('catalogo_categoria_productos', function (Blueprint $table) {
                $table->dropColumn('extension_perfumeria');
            });
        }
    }

    private function seedPerfumeria(): int
    {
        $row = DB::table('extensiones_producto')->where('codigo', 'perfumeria')->first();
        if ($row) {
            return (int) $row->id;
        }

        return (int) DB::table('extensiones_producto')->insertGetId([
            'codigo' => 'perfumeria',
            'nombre' => 'Perfumería',
            'descripcion' => 'Pirámide olfativa, notas y fases para productos de perfumería',
            'version' => '1',
            'habilitada' => true,
            'configuracion_json' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function backfillAsignaciones(int $extensionId): void
    {
        $categoriaIds = collect();

        if (Schema::hasColumn('catalogo_categoria_productos', 'extension_perfumeria')) {
            $categoriaIds = $categoriaIds->merge(
                DB::table('catalogo_categoria_productos')
                    ->where('extension_perfumeria', true)
                    ->pluck('id')
            );
        }

        if (Schema::hasTable('producto_notas_olfativas') && Schema::hasTable('productos')) {
            $categoriaIds = $categoriaIds->merge(
                DB::table('producto_notas_olfativas')
                    ->join('productos', 'productos.id', '=', 'producto_notas_olfativas.producto_id')
                    ->whereNotNull('productos.categoria_id')
                    ->distinct()
                    ->pluck('productos.categoria_id')
            );
        }

        foreach ($categoriaIds->unique()->filter() as $categoriaId) {
            $exists = DB::table('categoria_extensiones')
                ->where('categoria_id', $categoriaId)
                ->where('extension_id', $extensionId)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('categoria_extensiones')->insert([
                'categoria_id' => $categoriaId,
                'extension_id' => $extensionId,
                'habilitada' => true,
                'heredable' => true,
                'configuracion_json' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('catalogo_categoria_productos', 'extension_perfumeria')) {
            Schema::table('catalogo_categoria_productos', function (Blueprint $table) {
                $table->boolean('extension_perfumeria')->default(false)->after('estado');
            });

            $ext = DB::table('extensiones_producto')->where('codigo', 'perfumeria')->first();
            if ($ext && Schema::hasTable('categoria_extensiones')) {
                $ids = DB::table('categoria_extensiones')
                    ->where('extension_id', $ext->id)
                    ->where('habilitada', true)
                    ->pluck('categoria_id');
                if ($ids->isNotEmpty()) {
                    DB::table('catalogo_categoria_productos')
                        ->whereIn('id', $ids)
                        ->update(['extension_perfumeria' => true]);
                }
            }
        }

        Schema::dropIfExists('categoria_extensiones');
        Schema::dropIfExists('extensiones_producto');
    }
};
