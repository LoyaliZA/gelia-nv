<?php

use App\Models\ApiAplicacion;
use App\Models\ApiAplicacionCampo;
use App\Models\ApiCampoRecurso;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** @var list<string> */
    private const SLUGS_SENSIBLES = [
        'nombre_razon_social',
        'rfc',
        'codigo_postal',
        'regimen_fiscal',
        'correo_electronico',
        'uso_factura',
    ];

    public function up(): void
    {
        ApiCampoRecurso::query()
            ->whereIn('slug', self::SLUGS_SENSIBLES)
            ->update(['es_sensible' => true]);

        $campos = ApiCampoRecurso::query()
            ->whereIn('slug', self::SLUGS_SENSIBLES)
            ->get();

        if ($campos->isEmpty()) {
            return;
        }

        // Grandfather: apps existentes conservan el acceso efectivo previo (default allow).
        foreach (ApiAplicacion::query()->cursor() as $aplicacion) {
            foreach ($campos as $campo) {
                ApiAplicacionCampo::firstOrCreate(
                    [
                        'api_aplicacion_id' => $aplicacion->id,
                        'api_campo_recurso_id' => $campo->id,
                    ],
                    ['habilitado' => true]
                );
            }
        }
    }

    public function down(): void
    {
        ApiCampoRecurso::query()
            ->where('slug', 'nombre_razon_social')
            ->update(['es_sensible' => false]);
    }
};
