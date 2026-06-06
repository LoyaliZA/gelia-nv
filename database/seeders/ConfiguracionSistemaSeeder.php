<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionSistema;
use Illuminate\Support\Facades\Cache;

class ConfiguracionSistemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configuraciones = [
            [
                'clave' => 'mail.default',
                'valor' => 'smtp',
                'tipo' => 'string',
                'grupo' => 'Mail',
                'descripcion' => 'Mailer por defecto',
            ],
            [
                'clave' => 'mail.mailers.smtp.host',
                'valor' => 'smtp.gmail.com',
                'tipo' => 'string',
                'grupo' => 'Mail',
                'descripcion' => 'Host SMTP',
            ],
            [
                'clave' => 'mail.mailers.smtp.port',
                'valor' => '587',
                'tipo' => 'integer',
                'grupo' => 'Mail',
                'descripcion' => 'Puerto SMTP',
            ],
            [
                'clave' => 'mail.mailers.smtp.username',
                'valor' => 'contacto.neobash@gmail.com',
                'tipo' => 'string',
                'grupo' => 'Mail',
                'descripcion' => 'Usuario SMTP',
            ],
            [
                'clave' => 'mail.mailers.smtp.password',
                'valor' => '', // TODO: Colocar password en la UI
                'tipo' => 'string',
                'grupo' => 'Mail',
                'descripcion' => 'Contraseña de Aplicación SMTP',
            ],
            [
                'clave' => 'mail.mailers.smtp.encryption',
                'valor' => 'tls',
                'tipo' => 'string',
                'grupo' => 'Mail',
                'descripcion' => 'Protocolo de encriptación (tls o ssl)',
            ],
            [
                'clave' => 'mail.from.address',
                'valor' => 'contacto.neobash@gmail.com',
                'tipo' => 'string',
                'grupo' => 'Mail',
                'descripcion' => 'Dirección de correo remitente',
            ],
            [
                'clave' => 'mail.from.name',
                'valor' => 'GELIA - Notificaciones',
                'tipo' => 'string',
                'grupo' => 'Mail',
                'descripcion' => 'Nombre del remitente',
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
