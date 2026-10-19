<?php

use App\Services\Listados\PorcentajesListadoService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gelia_settings')
            || !Schema::hasTable('catalogo_listas_descuento')
            || !Schema::hasTable('catalogo_porcentajes_listado_lista')) {
            return;
        }

        $settings = DB::table('gelia_settings')->pluck('value', 'key')->all();

        app(PorcentajesListadoService::class)->sincronizarSettingsACatalogo($settings);
    }

    public function down(): void
    {
        // Los porcentajes vigentes viven en gelia_settings; revertir los dejaría otra vez desfasados.
    }
};
