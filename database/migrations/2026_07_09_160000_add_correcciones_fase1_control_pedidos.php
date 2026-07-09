<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_envios_tienda', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('es_otro')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::table('catalogo_paqueterias_pedido', function (Blueprint $table) {
            $table->decimal('costo_seguro_default', 12, 2)->default(0)->after('nombre');
        });

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->foreignId('catalogo_envio_tienda_id')->nullable()->after('catalogo_zona_id')->constrained('catalogo_envios_tienda')->nullOnDelete();
            $table->string('envio_tienda_otro')->nullable()->after('catalogo_envio_tienda_id');
            $table->decimal('peso_volumetrico_kg', 10, 4)->nullable()->after('peso_real_kg');
            $table->decimal('peso_con_productos_kg', 10, 4)->nullable()->after('peso_volumetrico_kg');
            $table->boolean('es_resguardo')->default(false)->after('envia_otra_persona');
            $table->boolean('envia_a_otra_persona')->default(false)->after('es_resguardo');
        });

        $now = now();

        DB::table('catalogo_envios_tienda')->insert([
            'nombre' => 'Otro',
            'es_otro' => true,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalogo_envio_tienda_id');
            $table->dropColumn([
                'envio_tienda_otro',
                'peso_volumetrico_kg',
                'peso_con_productos_kg',
                'es_resguardo',
                'envia_a_otra_persona',
            ]);
        });

        Schema::table('catalogo_paqueterias_pedido', function (Blueprint $table) {
            $table->dropColumn('costo_seguro_default');
        });

        Schema::dropIfExists('catalogo_envios_tienda');
    }
};
