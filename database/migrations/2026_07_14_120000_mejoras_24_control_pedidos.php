<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos_bma', 'requiere_factura')) {
                $table->dropColumn('requiere_factura');
            }
        });

        if (Schema::hasColumn('pedidos_bma', 'peso_con_productos_kg')
            && ! Schema::hasColumn('pedidos_bma', 'peso_cobrado_guia_kg')) {
            Schema::table('pedidos_bma', function (Blueprint $table) {
                $table->decimal('peso_cobrado_guia_kg', 10, 4)->nullable()->after('peso_volumetrico_kg');
            });

            DB::table('pedidos_bma')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('pedidos_bma')
                        ->where('id', $row->id)
                        ->update(['peso_cobrado_guia_kg' => $row->peso_con_productos_kg ?? null]);
                }
            });

            Schema::table('pedidos_bma', function (Blueprint $table) {
                $table->dropColumn('peso_con_productos_kg');
            });
        }

        Schema::table('pedidos_bma', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos_bma', 'anexar_remision')) {
                $after = Schema::hasColumn('pedidos_bma', 'es_resguardo') ? 'es_resguardo' : null;
                if ($after) {
                    $table->boolean('anexar_remision')->default(false)->after($after);
                } else {
                    $table->boolean('anexar_remision')->default(false);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos_bma', 'anexar_remision')) {
                $table->dropColumn('anexar_remision');
            }
        });

        if (Schema::hasColumn('pedidos_bma', 'peso_cobrado_guia_kg')
            && ! Schema::hasColumn('pedidos_bma', 'peso_con_productos_kg')) {
            Schema::table('pedidos_bma', function (Blueprint $table) {
                $table->decimal('peso_con_productos_kg', 10, 4)->nullable()->after('peso_volumetrico_kg');
            });

            DB::table('pedidos_bma')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('pedidos_bma')
                        ->where('id', $row->id)
                        ->update(['peso_con_productos_kg' => $row->peso_cobrado_guia_kg ?? null]);
                }
            });

            Schema::table('pedidos_bma', function (Blueprint $table) {
                $table->dropColumn('peso_cobrado_guia_kg');
            });
        }

        Schema::table('pedidos_bma', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos_bma', 'requiere_factura')) {
                $table->boolean('requiere_factura')->default(false);
            }
        });
    }
};
