<?php

use App\Support\DepartamentoLogoAssets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->string('logo_key_claro', 64)->nullable()->after('codigo');
            $table->string('logo_key_oscuro', 64)->nullable()->after('logo_key_claro');
        });

        if (Schema::hasColumn('departamentos', 'logo_key')) {
            foreach (DB::table('departamentos')->select('id', 'logo_key')->get() as $row) {
                DB::table('departamentos')->where('id', $row->id)->update([
                    'logo_key_claro' => $row->logo_key,
                ]);
            }

            Schema::table('departamentos', function (Blueprint $table) {
                $table->dropColumn('logo_key');
            });
        }

        $keys = DepartamentoLogoAssets::keysDisponibles();
        $rows = DB::table('departamentos')->select('id', 'logo_key_claro')->get();
        foreach ($rows as $row) {
            $claro = (string) ($row->logo_key_claro ?? '');
            if ($claro === '' || ! str_ends_with($claro, '_negro')) {
                continue;
            }
            $oscuro = substr($claro, 0, -strlen('_negro')).'_blanco';
            if (! in_array($oscuro, $keys, true)) {
                continue;
            }
            DB::table('departamentos')->where('id', $row->id)->update(['logo_key_oscuro' => $oscuro]);
        }
    }

    public function down(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->string('logo_key', 64)->nullable()->after('codigo');
        });

        foreach (DB::table('departamentos')->select('id', 'logo_key_claro')->get() as $row) {
            DB::table('departamentos')->where('id', $row->id)->update([
                'logo_key' => $row->logo_key_claro,
            ]);
        }
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropColumn(['logo_key_claro', 'logo_key_oscuro']);
        });
    }
};
