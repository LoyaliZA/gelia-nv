<?php

namespace Database\Seeders;

use App\Models\ConfiguracionSistema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class WebAuthnConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $appUrl = (string) env('APP_URL', 'http://localhost');
        $appHost = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';

        $configuraciones = [
            [
                'clave' => 'webauthn.enabled',
                'valor' => filter_var(env('WEBAUTHN_ENABLED', false), FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
                'tipo' => 'boolean',
                'grupo' => 'WebAuthn',
                'descripcion' => 'Habilitar login y registro con passkeys (huella, Face ID, llaves de seguridad)',
            ],
            [
                'clave' => 'webauthn.relying_party.name',
                'valor' => (string) env('WEBAUTHN_RP_NAME', env('WEBAUTHN_NAME', env('APP_NAME', 'GELIA-NV'))),
                'tipo' => 'string',
                'grupo' => 'WebAuthn',
                'descripcion' => 'Nombre visible del sitio al registrar passkeys (Relying Party)',
            ],
            [
                'clave' => 'webauthn.relying_party.id',
                'valor' => (string) env('WEBAUTHN_RP_ID', env('WEBAUTHN_ID', $appHost)),
                'tipo' => 'string',
                'grupo' => 'WebAuthn',
                'descripcion' => 'Dominio del sitio (sin esquema ni puerto). Debe coincidir con el host de producción.',
            ],
            [
                'clave' => 'webauthn.origins',
                'valor' => (string) env('WEBAUTHN_ORIGINS', $appUrl),
                'tipo' => 'string',
                'grupo' => 'WebAuthn',
                'descripcion' => 'Orígenes permitidos separados por coma (ej. https://app.ejemplo.com,http://localhost)',
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
