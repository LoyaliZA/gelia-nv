<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('almacenes', function (Blueprint $table) {
            $table->boolean('visible_en_pedidos')->default(false)->after('activo');
        });

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('cliente_id')->constrained('almacenes')->nullOnDelete();
        });

        $mapaSalida = DB::table('catalogo_almacenes_salida')->get(['id', 'nombre']);

        foreach ($mapaSalida as $salida) {
            $almacen = DB::table('almacenes')
                ->where(function ($query) use ($salida) {
                    $nombre = mb_strtoupper($salida->nombre);
                    $query->whereRaw('UPPER(nombre) = ?', [$nombre])
                        ->orWhereRaw('UPPER(codigo) = ?', [$nombre]);
                })
                ->first();

            if ($almacen) {
                DB::table('pedidos_bma')
                    ->where('catalogo_almacen_salida_id', $salida->id)
                    ->update(['almacen_id' => $almacen->id]);

                DB::table('almacenes')
                    ->where('id', $almacen->id)
                    ->update(['visible_en_pedidos' => true]);
            }
        }

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalogo_almacen_salida_id');
        });

        Schema::dropIfExists('catalogo_almacenes_salida');
    }

    public function down(): void
    {
        Schema::create('catalogo_almacenes_salida', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->foreignId('catalogo_almacen_salida_id')->nullable()->after('cliente_id')->constrained('catalogo_almacenes_salida')->nullOnDelete();
        });

        $pedidos = DB::table('pedidos_bma')->whereNotNull('almacen_id')->get(['id', 'almacen_id']);

        foreach ($pedidos as $pedido) {
            $almacen = DB::table('almacenes')->where('id', $pedido->almacen_id)->first();
            if (!$almacen) {
                continue;
            }

            $salidaId = DB::table('catalogo_almacenes_salida')->where('nombre', $almacen->nombre)->value('id');
            if (!$salidaId) {
                $salidaId = DB::table('catalogo_almacenes_salida')->insertGetId([
                    'nombre' => $almacen->nombre,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('pedidos_bma')->where('id', $pedido->id)->update(['catalogo_almacen_salida_id' => $salidaId]);
        }

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('almacen_id');
        });

        Schema::table('almacenes', function (Blueprint $table) {
            $table->dropColumn('visible_en_pedidos');
        });
    }
};
