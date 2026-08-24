<?php

use App\Models\ConfiguracionSistema;
use App\Services\ControlPedidos\FormularioProgresivoPedidoBmaConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (FormularioProgresivoPedidoBmaConfig::semillas() as $clave => $meta) {
            ConfiguracionSistema::updateOrCreate(
                ['clave' => $clave],
                [
                    'valor' => $meta['valor'],
                    'tipo' => $meta['tipo'],
                    'grupo' => 'ControlPedidos',
                    'descripcion' => $meta['descripcion'],
                ]
            );
        }
    }

    public function down(): void
    {
        ConfiguracionSistema::whereIn(
            'clave',
            array_keys(FormularioProgresivoPedidoBmaConfig::semillas())
        )->delete();
    }
};
