<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_cajas', function (Blueprint $table) {
            $table->decimal('largo', 10, 2)->nullable()->after('orden');
            $table->decimal('ancho', 10, 2)->nullable()->after('largo');
            $table->decimal('alto', 10, 2)->nullable()->after('ancho');
            $table->decimal('peso_real_kg', 12, 4)->nullable()->after('alto');
            $table->decimal('peso_volumetrico_kg', 12, 4)->nullable()->after('peso_real_kg');
            $table->decimal('peso_cobrado_kg', 12, 4)->nullable()->after('peso_volumetrico_kg');
            $table->foreignId('catalogo_tipo_guia_id')->nullable()->after('peso_cobrado_kg')
                ->constrained('catalogo_tipos_guia_pedido')->nullOnDelete();
        });

        $this->expandirCantidadesMayorAUno();
        $this->backfillMedidasCatalogo();
    }

    public function down(): void
    {
        Schema::table('pedido_bma_cajas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalogo_tipo_guia_id');
            $table->dropColumn([
                'largo',
                'ancho',
                'alto',
                'peso_real_kg',
                'peso_volumetrico_kg',
                'peso_cobrado_kg',
            ]);
        });
    }

    private function expandirCantidadesMayorAUno(): void
    {
        $filas = DB::table('pedido_bma_cajas')->where('cantidad', '>', 1)->orderBy('id')->get();

        foreach ($filas as $fila) {
            $extras = ((int) $fila->cantidad) - 1;
            for ($i = 0; $i < $extras; $i++) {
                DB::table('pedido_bma_cajas')->insert([
                    'pedido_bma_id' => $fila->pedido_bma_id,
                    'catalogo_tipo_caja_id' => $fila->catalogo_tipo_caja_id,
                    'cantidad' => 1,
                    'orden' => ((int) $fila->orden) + $i + 1,
                    'created_at' => $fila->created_at,
                    'updated_at' => $fila->updated_at,
                ]);
            }
            DB::table('pedido_bma_cajas')->where('id', $fila->id)->update(['cantidad' => 1]);
        }

        $pedidoIds = DB::table('pedido_bma_cajas')->distinct()->pluck('pedido_bma_id');
        foreach ($pedidoIds as $pedidoId) {
            $cajas = DB::table('pedido_bma_cajas')
                ->where('pedido_bma_id', $pedidoId)
                ->orderBy('orden')
                ->orderBy('id')
                ->get(['id']);
            $orden = 0;
            foreach ($cajas as $caja) {
                DB::table('pedido_bma_cajas')->where('id', $caja->id)->update(['orden' => $orden++]);
            }
        }
    }

    private function backfillMedidasCatalogo(): void
    {
        $now = now();
        $tipos = [
            ['nombre' => 'CAJA #30', 'largo' => 21, 'ancho' => 17, 'alto' => 14, 'peso_volumetrico' => 0.9996],
            ['nombre' => 'CAJA #28', 'largo' => 31, 'ancho' => 23, 'alto' => 14, 'peso_volumetrico' => 1.9964],
            ['nombre' => 'CAJA #42', 'largo' => 32, 'ancho' => 25, 'alto' => 24, 'peso_volumetrico' => 3.84],
            ['nombre' => 'CAJA #56', 'largo' => 45, 'ancho' => 29, 'alto' => 27, 'peso_volumetrico' => 7.047],
            ['nombre' => 'CAJA #220', 'largo' => 41, 'ancho' => 31, 'alto' => 32, 'peso_volumetrico' => 8.1344],
            ['nombre' => 'CAJA #202', 'largo' => 46, 'ancho' => 32, 'alto' => 35, 'peso_volumetrico' => 10.304],
            ['nombre' => 'CAJA #21', 'largo' => 35, 'ancho' => 35, 'alto' => 22, 'peso_volumetrico' => 5.39],
            ['nombre' => 'CAJA UNIVERSO GRANDE', 'largo' => 57, 'ancho' => 41, 'alto' => 42, 'peso_volumetrico' => 19.6308],
            ['nombre' => 'UNIVERSO #202', 'largo' => 46, 'ancho' => 33, 'alto' => 40, 'peso_volumetrico' => 12.144],
            ['nombre' => 'CAJA #40', 'largo' => 34, 'ancho' => 33, 'alto' => 17, 'peso_volumetrico' => 3.8148],
            ['nombre' => 'CAJA #349', 'largo' => 21, 'ancho' => 21, 'alto' => 21, 'peso_volumetrico' => 1.8522],
            ['nombre' => 'CAJA #85', 'largo' => 17, 'ancho' => 14, 'alto' => 13, 'peso_volumetrico' => 0.6188],
            ['nombre' => 'CAJA #32', 'largo' => 28, 'ancho' => 22, 'alto' => 42, 'peso_volumetrico' => 5.1744],
            ['nombre' => 'CAJA #33', 'largo' => 30, 'ancho' => 15, 'alto' => 15, 'peso_volumetrico' => 1.35],
            ['nombre' => 'CAJA #31', 'largo' => 31, 'ancho' => 22, 'alto' => 15, 'peso_volumetrico' => 2.05],
        ];

        foreach ($tipos as $caja) {
            $medidas = "{$caja['largo']} x {$caja['ancho']} x {$caja['alto']} cm";
            $existe = DB::table('catalogo_tipos_caja_pedido')->where('nombre', $caja['nombre'])->exists();
            $row = [
                'peso_volumetrico' => $caja['peso_volumetrico'],
                'largo' => $caja['largo'],
                'ancho' => $caja['ancho'],
                'alto' => $caja['alto'],
                'medidas' => $medidas,
                'activo' => true,
                'updated_at' => $now,
            ];
            if (! $existe) {
                $row['created_at'] = $now;
            }
            DB::table('catalogo_tipos_caja_pedido')->updateOrInsert(
                ['nombre' => $caja['nombre']],
                $row
            );
        }

        DB::table('catalogo_tipos_caja_pedido')
            ->where('nombre', 'CAJA #8')
            ->update(['activo' => false, 'updated_at' => $now]);
    }
};
