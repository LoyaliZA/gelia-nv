<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Cache;

class WebPushConfiguracionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configuraciones = [
            [
                'clave' => 'webpush.vapid.subject',
                'valor' => 'http://localhost', // Cambiar en UI al dominio real
                'tipo' => 'string',
                'grupo' => 'WebPush',
                'descripcion' => 'URL de contacto o email (mailto:) del servidor VAPID',
            ],
            [
                'clave' => 'webpush.vapid.public_key',
                'valor' => '',
                'tipo' => 'string',
                'grupo' => 'WebPush',
                'descripcion' => 'Clave pública de WebPush (VAPID)',
            ],
            [
                'clave' => 'webpush.vapid.private_key',
                'valor' => '',
                'tipo' => 'string',
                'grupo' => 'WebPush',
                'descripcion' => 'Clave privada de WebPush (VAPID)',
            ],
        ];

        foreach ($configuraciones as $config) {
            ConfiguracionSistema::updateOrCreate(
                ['clave' => $config['clave']],
                $config
            );
        }

        // Limpiar la caché después de sembrar
        Cache::forget('configuraciones_sistema_globales');
    }
}
