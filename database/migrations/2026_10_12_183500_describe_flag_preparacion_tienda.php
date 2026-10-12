<?php

use App\Services\ControlPedidos\PreparacionTiendaConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuraciones_sistema')
            ->where('clave', PreparacionTiendaConfig::CLAVE_FLAG)
            ->update([
                'descripcion' => 'Preparación en tienda. Active el módulo y las modalidades con los interruptores de esta configuración.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('configuraciones_sistema')
            ->where('clave', PreparacionTiendaConfig::CLAVE_FLAG)
            ->update([
                'descripcion' => 'Feature flag preparación Tienda (Control Pedidos)',
                'updated_at' => now(),
            ]);
    }
};
