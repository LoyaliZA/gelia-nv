<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $nuevosEstatus = [
            ['codigo_interno' => 'PENDIENTE_GUIA', 'nombre_visual' => 'Pendiente de guía', 'color_hex' => '#A855F7', 'fase_ciclo' => 'PENDIENTE_DE_GUIA', 'orden' => 7],
            ['codigo_interno' => 'ENTREGADO', 'nombre_visual' => 'Entregado', 'color_hex' => '#10B981', 'fase_ciclo' => 'ENTREGADO', 'orden' => 8],
            ['codigo_interno' => 'ENVIADO', 'nombre_visual' => 'Enviado', 'color_hex' => '#22C55E', 'fase_ciclo' => 'ENVIADO', 'orden' => 9],
        ];

        foreach ($nuevosEstatus as $row) {
            $existe = DB::table('catalogo_estatus_pedidos')
                ->where('fase_ciclo', $row['fase_ciclo'])
                ->exists();

            if (!$existe) {
                DB::table('catalogo_estatus_pedidos')->insert(array_merge($row, [
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        $this->migrarPedidosEnRuta();

        PermisoCatalogoMigracion::registrar('control_pedidos.delegado');
    }

    public function down(): void
    {
        $fasesNuevas = ['PENDIENTE_DE_GUIA', 'ENTREGADO', 'ENVIADO'];
        $idsNuevos = DB::table('catalogo_estatus_pedidos')
            ->whereIn('fase_ciclo', $fasesNuevas)
            ->pluck('id');

        $enRutaId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'EN_RUTA')
            ->value('id');

        if ($enRutaId && $idsNuevos->isNotEmpty()) {
            DB::table('pedidos_bma')
                ->whereIn('catalogo_estatus_pedido_id', $idsNuevos)
                ->update(['catalogo_estatus_pedido_id' => $enRutaId]);
        }

        DB::table('catalogo_estatus_pedidos')
            ->whereIn('fase_ciclo', $fasesNuevas)
            ->delete();

        \Spatie\Permission\Models\Permission::where('name', 'control_pedidos.delegado')->delete();
    }

    private function migrarPedidosEnRuta(): void
    {
        $enRutaId = DB::table('catalogo_estatus_pedidos')
            ->where('fase_ciclo', 'EN_RUTA')
            ->value('id');

        if (!$enRutaId) {
            return;
        }

        $idsDestino = DB::table('catalogo_estatus_pedidos')
            ->whereIn('fase_ciclo', ['PENDIENTE_DE_GUIA', 'ENTREGADO', 'ENVIADO'])
            ->pluck('id', 'fase_ciclo');

        $pedidos = DB::table('pedidos_bma')
            ->where('catalogo_estatus_pedido_id', $enRutaId)
            ->get(['id', 'numero_rastreo', 'catalogo_paqueteria_id']);

        foreach ($pedidos as $pedido) {
            $faseDestino = $this->resolverFaseMigracion($pedido);

            DB::table('pedidos_bma')
                ->where('id', $pedido->id)
                ->update(['catalogo_estatus_pedido_id' => $idsDestino[$faseDestino]]);
        }
    }

    private function resolverFaseMigracion(object $pedido): string
    {
        if (!empty($pedido->numero_rastreo)) {
            return 'ENVIADO';
        }

        if ($pedido->catalogo_paqueteria_id) {
            $categoria = DB::table('catalogo_paqueterias_pedido')
                ->where('id', $pedido->catalogo_paqueteria_id)
                ->value('categoria');

            if ($categoria === 'comercial') {
                return 'PENDIENTE_DE_GUIA';
            }
        }

        return 'ENTREGADO';
    }
};
