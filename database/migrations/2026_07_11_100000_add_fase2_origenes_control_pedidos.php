<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('origenes_pedido', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('requiere_logistica')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $now = now();
        $origenes = [
            ['nombre' => 'Mostrador', 'requiere_logistica' => false],
            ['nombre' => 'Envío Foráneo', 'requiere_logistica' => true],
            ['nombre' => 'E-commerce', 'requiere_logistica' => true],
            ['nombre' => 'Mayoristas', 'requiere_logistica' => true],
        ];

        foreach ($origenes as $origen) {
            DB::table('origenes_pedido')->insert(array_merge($origen, [
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->foreignId('origen_id')->nullable()->after('cliente_id')->constrained('origenes_pedido')->nullOnDelete();
        });

        $mostradorId = DB::table('origenes_pedido')->where('nombre', 'Mostrador')->value('id');
        $foraneoId = DB::table('origenes_pedido')->where('nombre', 'Envío Foráneo')->value('id');

        DB::table('pedidos_bma')
            ->where(function ($q) {
                $q->whereNotNull('catalogo_paqueteria_id')
                    ->orWhereNotNull('codigo_postal')
                    ->orWhereNotNull('domicilio_entrega');
            })
            ->update(['origen_id' => $foraneoId]);

        DB::table('pedidos_bma')
            ->whereNull('origen_id')
            ->update(['origen_id' => $mostradorId]);

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->unsignedSmallInteger('numero_cajas')->nullable()->default(null)->change();
            $table->decimal('costo_envio', 12, 2)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origen_id');
            $table->unsignedSmallInteger('numero_cajas')->default(1)->change();
            $table->decimal('costo_envio', 12, 2)->default(0)->change();
        });

        Schema::dropIfExists('origenes_pedido');
    }
};
