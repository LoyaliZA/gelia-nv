<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Default de sistema: sidebar profesional para todos.
 * No elimina layouts legacy: el usuario puede volver a flotante/fijo en Perfil.
 */
return new class extends Migration
{
    private const LAYOUT = 'professional_left';

    private const MOBILE = 'mobile_topbar';

    public function up(): void
    {
        if (Schema::hasTable('configuraciones_usuarios')) {
            $this->patchJsonColumn('configuraciones_usuarios', 'tema_visual', function (array $tema): array {
                $tema['layout_sidebar'] = self::LAYOUT;
                $tema['layout_sidebar_mobile'] = self::MOBILE;

                return $tema;
            });
        }

        if (Schema::hasTable('personalizacion_temas')) {
            $this->patchJsonColumn('personalizacion_temas', 'configuracion', function (array $cfg): array {
                $cfg['layout_sidebar'] = self::LAYOUT;
                $cfg['layout_sidebar_mobile'] = self::MOBILE;

                return $cfg;
            });
        }
    }

    public function down(): void
    {
        // No revertimos preferencias de usuario: un down ciego a floating_left
        // pisaría elecciones hechas después del deploy.
    }

    /**
     * @param  callable(array): array  $mutator
     */
    private function patchJsonColumn(string $table, string $column, callable $mutator): void
    {
        DB::table($table)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column, $mutator) {
                foreach ($rows as $row) {
                    $raw = $row->{$column} ?? null;
                    $decoded = [];
                    if (is_string($raw) && $raw !== '') {
                        $decoded = json_decode($raw, true) ?: [];
                    } elseif (is_array($raw)) {
                        $decoded = $raw;
                    }

                    $next = $mutator($decoded);
                    DB::table($table)->where('id', $row->id)->update([
                        $column => json_encode($next),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
};
