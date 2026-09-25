<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_traspasos', function (Blueprint $table) {
            $table->string('estado_fisico_general_origen', 32)->nullable()->after('total_piezas');
            $table->string('estado_fisico_general_cedis', 32)->nullable()->after('estado_fisico_general_origen');
        });

        Schema::create('solicitud_traspaso_revisiones_producto', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitud_traspaso_id');
            $table->string('momento', 16);
            $table->unsignedBigInteger('solicitud_traspaso_producto_id');
            $table->unsignedBigInteger('producto_id')->nullable();
            $table->string('sku', 64)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->string('descripcion_producto', 255);
            $table->string('estado_fisico', 32);
            $table->text('comentario')->nullable();
            $table->boolean('unica_pieza')->default(false);
            $table->boolean('mejor_ejemplar')->default(false);
            $table->json('evidencia_paths')->nullable();
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('solicitud_traspaso_id', 'st_rev_prod_sol_fk')
                ->references('id')->on('solicitudes_traspasos')->cascadeOnDelete();
            $table->foreign('solicitud_traspaso_producto_id', 'st_rev_prod_linea_fk')
                ->references('id')->on('solicitud_traspaso_productos')->cascadeOnDelete();
            $table->index(['solicitud_traspaso_id', 'momento'], 'st_rev_prod_sol_mom_idx');
        });

        if (Schema::hasTable('solicitud_traspaso_detalle_danos')) {
            $legacy = DB::table('solicitud_traspaso_detalle_danos')->get();
            foreach ($legacy as $row) {
                $linea = DB::table('solicitud_traspaso_productos')
                    ->where('id', $row->solicitud_traspaso_producto_id)
                    ->first();
                if (! $linea) {
                    continue;
                }

                $paths = $row->paths;
                if (is_string($paths)) {
                    $paths = json_decode($paths, true) ?: [];
                }
                if (! is_array($paths)) {
                    $paths = [];
                }

                DB::table('solicitud_traspaso_revisiones_producto')->insert([
                    'solicitud_traspaso_id' => $row->solicitud_traspaso_id,
                    'momento' => 'cedis',
                    'solicitud_traspaso_producto_id' => $row->solicitud_traspaso_producto_id,
                    'producto_id' => $linea->producto_id,
                    'sku' => $linea->sku,
                    'orden' => 0,
                    'descripcion_producto' => "{$linea->sku} — {$linea->descripcion}",
                    'estado_fisico' => 'danado',
                    'comentario' => $row->motivo,
                    'unica_pieza' => false,
                    'mejor_ejemplar' => false,
                    'evidencia_paths' => json_encode(array_values($paths)),
                    'registrado_por_id' => $row->reportado_por_id,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }

            Schema::dropIfExists('solicitud_traspaso_detalle_danos');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_traspaso_revisiones_producto');

        Schema::table('solicitudes_traspasos', function (Blueprint $table) {
            $table->dropColumn(['estado_fisico_general_origen', 'estado_fisico_general_cedis']);
        });
    }
};
