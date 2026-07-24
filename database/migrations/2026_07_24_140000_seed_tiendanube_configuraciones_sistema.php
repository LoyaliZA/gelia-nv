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
        return [
            [
                'clave' => 'tiendanube.app_id',
                'valor' => '',
                'tipo' => 'string',
                'grupo' => 'Tiendanube',
                'descripcion' => 'App ID partner',
            ],
            [
                'clave' => 'tiendanube.app_secret',
                'valor' => '',
                'tipo' => 'string',
                'grupo' => 'Tiendanube',
                'descripcion' => 'Secret (webhooks)',
            ],
            [
                'clave' => 'tiendanube.store_id',
                'valor' => '',
                'tipo' => 'string',
                'grupo' => 'Tiendanube',
                'descripcion' => 'Store ID',
            ],
            [
                'clave' => 'tiendanube.access_token',
                'valor' => '',
                'tipo' => 'string',
                'grupo' => 'Tiendanube',
                'descripcion' => 'Access token',
            ],
            [
                'clave' => 'tiendanube.api_base',
                'valor' => 'https://api.tiendanube.com/v1',
                'tipo' => 'string',
                'grupo' => 'Tiendanube',
                'descripcion' => 'Base API',
            ],
            [
                'clave' => 'tiendanube.user_agent',
                'valor' => 'Gelianv',
                'tipo' => 'string',
                'grupo' => 'Tiendanube',
                'descripcion' => 'User-Agent',
            ],
            [
                'clave' => 'tiendanube.per_page',
                'valor' => '50',
                'tipo' => 'integer',
                'grupo' => 'Tiendanube',
                'descripcion' => 'Página sync',
            ],
            [
                'clave' => 'tiendanube.webhook_url',
                'valor' => '',
                'tipo' => 'string',
                'grupo' => 'Tiendanube',
                'descripcion' => 'URL webhooks (vacío = {APP_URL}/webhooks/tiendanube)',
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
