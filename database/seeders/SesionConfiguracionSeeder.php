<?php

namespace Database\Seeders;

use App\Models\ConfiguracionSistema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SesionConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $configuraciones = [
            [
                'clave' => 'session.lifetime',
                'valor' => '480',
                'tipo' => 'integer',
                'grupo' => 'Sesiones',
                'descripcion' => 'Minutos de inactividad antes de expirar la sesión',
            ],
            [
                'clave' => 'session.expire_on_close',
                'valor' => 'false',
                'tipo' => 'boolean',
                'grupo' => 'Sesiones',
                'descripcion' => 'Expirar sesión al cerrar el navegador',
            ],
            [
                'clave' => 'sesiones.jornada_cierre_activo',
                'valor' => 'true',
                'tipo' => 'boolean',
                'grupo' => 'Sesiones',
                'descripcion' => 'Cerrar todas las sesiones al terminar la jornada laboral',
            ],
            [
                'clave' => 'sesiones.jornada_hora_fin',
                'valor' => '18:00',
                'tipo' => 'string',
                'grupo' => 'Sesiones',
                'descripcion' => 'Hora de fin de jornada (formato HH:MM, zona horaria configurada abajo)',
            ],
            [
                'clave' => 'sesiones.jornada_zona_horaria',
                'valor' => 'America/Mexico_City',
                'tipo' => 'string',
                'grupo' => 'Sesiones',
                'descripcion' => 'Zona horaria para calcular el cierre de jornada',
            ],
        ];

        foreach ($configuraciones as $config) {
            ConfiguracionSistema::updateOrCreate(
                ['clave' => $config['clave']],
                $config
            );
        }

        Cache::forget('configuraciones_sistema_globales');
    }
}
