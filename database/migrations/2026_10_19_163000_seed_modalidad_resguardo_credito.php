<?php

use App\Models\ControlPedidos\CatalogoModalidadPreparacionPedido;
use App\Services\ControlPedidos\PreparacionTiendaConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo_modalidades_preparacion_pedido')) {
            return;
        }

        $now = now();
        DB::table('catalogo_modalidades_preparacion_pedido')->insertOrIgnore([
            'codigo' => CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO,
            'nombre' => 'Resguardo con crédito',
            'descripcion' => 'Recolección local con pago a crédito; la mercancía queda resguardada hasta la entrega.',
            'area_responsable_codigo' => 'TIENDA',
            'requisitos_json' => json_encode([
                'evidencia_general_obligatoria' => true,
                'evidencia_por_producto' => false,
                'estados_fisicos_permitidos' => ['bueno', 'regular', 'malo', 'danado', 'sin_existencia'],
                'resguardo' => true,
            ]),
            'activo' => true,
            'orden' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if (! Schema::hasTable('configuraciones_sistema')) {
            return;
        }

        $row = DB::table('configuraciones_sistema')
            ->where('clave', PreparacionTiendaConfig::CLAVE_FLAG)
            ->first();
        if (! $row) {
            return;
        }

        $flag = json_decode((string) $row->valor, true);
        if (! is_array($flag)) {
            return;
        }

        $habilitadas = array_values(array_filter(array_map('strval', $flag['modalidades_habilitadas'] ?? [])));
        if (! in_array(CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_TRANSFERENCIA, $habilitadas, true)
            || in_array(CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO, $habilitadas, true)) {
            return;
        }

        $habilitadas[] = CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO;
        $flag['modalidades_habilitadas'] = $habilitadas;

        DB::table('configuraciones_sistema')
            ->where('clave', PreparacionTiendaConfig::CLAVE_FLAG)
            ->update([
                'valor' => json_encode($flag, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);

        app(PreparacionTiendaConfig::class)->olvidarCache();
    }

    public function down(): void
    {
        if (Schema::hasTable('configuraciones_sistema')) {
            $row = DB::table('configuraciones_sistema')
                ->where('clave', PreparacionTiendaConfig::CLAVE_FLAG)
                ->first();
            if ($row) {
                $flag = json_decode((string) $row->valor, true);
                if (is_array($flag)) {
                    $flag['modalidades_habilitadas'] = array_values(array_filter(
                        array_map('strval', $flag['modalidades_habilitadas'] ?? []),
                        fn (string $codigo) => $codigo !== CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO
                    ));
                    DB::table('configuraciones_sistema')
                        ->where('clave', PreparacionTiendaConfig::CLAVE_FLAG)
                        ->update([
                            'valor' => json_encode($flag, JSON_UNESCAPED_UNICODE),
                            'updated_at' => now(),
                        ]);
                }
            }
        }

        if (! Schema::hasTable('catalogo_modalidades_preparacion_pedido')) {
            return;
        }

        $modalidad = DB::table('catalogo_modalidades_preparacion_pedido')
            ->where('codigo', CatalogoModalidadPreparacionPedido::CODIGO_RECOGE_TIENDA_CREDITO)
            ->first();
        if (! $modalidad) {
            return;
        }

        $enUso = Schema::hasTable('pedido_bma_tareas_preparacion')
            && DB::table('pedido_bma_tareas_preparacion')
                ->where('catalogo_modalidad_preparacion_id', $modalidad->id)
                ->exists();
        if (! $enUso) {
            DB::table('catalogo_modalidades_preparacion_pedido')->where('id', $modalidad->id)->delete();
        }
    }
};
