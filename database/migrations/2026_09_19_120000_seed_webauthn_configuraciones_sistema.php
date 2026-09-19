<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @return list<array{clave: string, valor: string, tipo: string, grupo: string, descripcion: string}> */
    private function filas(): array
    {
        $appUrl = (string) env('APP_URL', 'http://localhost');
        $appHost = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';

        return [
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
    }

    public function up(): void
    {
        if (! Schema::hasTable('configuraciones_sistema')) {
            return;
        }

        $now = now();

        foreach ($this->filas() as $fila) {
            $existe = DB::table('configuraciones_sistema')->where('clave', $fila['clave'])->exists();
            if ($existe) {
                continue;
            }

            DB::table('configuraciones_sistema')->insert([
                ...$fila,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Cache::forget('configuraciones_sistema_globales');
    }

    public function down(): void
    {
        if (! Schema::hasTable('configuraciones_sistema')) {
            return;
        }

        $claves = array_column($this->filas(), 'clave');
        DB::table('configuraciones_sistema')->whereIn('clave', $claves)->delete();
        Cache::forget('configuraciones_sistema_globales');
    }
};
