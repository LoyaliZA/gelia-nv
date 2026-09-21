<?php

use App\Models\PuntoVenta\PdvPantallaSalaToken;
use App\Services\PuntoVenta\Pantallas\ResolverTokenPantallaSalaPdvService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_pantalla_sala_tokens', function (Blueprint $table) {
            $table->string('token_publico', 64)->nullable()->unique()->after('token_hash');
        });

        $resolver = app(ResolverTokenPantallaSalaPdvService::class);

        PdvPantallaSalaToken::query()
            ->whereNull('token_publico')
            ->orderBy('id')
            ->each(function (PdvPantallaSalaToken $registro) use ($resolver): void {
                $generado = $resolver->generarTokenPlano();
                $registro->update([
                    'token_publico' => $generado['token'],
                    'token_hash' => $generado['hash'],
                    'expira_en' => null,
                ]);
            });

        $duplicados = DB::table('pdv_pantalla_sala_tokens')
            ->select('sucursal_id')
            ->groupBy('sucursal_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('sucursal_id');

        foreach ($duplicados as $sucursalId) {
            $canonical = PdvPantallaSalaToken::query()
                ->where('sucursal_id', $sucursalId)
                ->orderByDesc('id')
                ->first();

            if (! $canonical instanceof PdvPantallaSalaToken) {
                continue;
            }

            PdvPantallaSalaToken::query()
                ->where('sucursal_id', $sucursalId)
                ->where('id', '!=', $canonical->id)
                ->delete();
        }

        Schema::table('pdv_pantalla_sala_tokens', function (Blueprint $table) {
            $table->string('token_publico', 64)->nullable(false)->change();
            $table->unique('sucursal_id', 'pdv_pantalla_sala_sucursal_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pdv_pantalla_sala_tokens', function (Blueprint $table) {
            $table->dropUnique('pdv_pantalla_sala_sucursal_unique');
            $table->dropColumn('token_publico');
        });
    }
};
