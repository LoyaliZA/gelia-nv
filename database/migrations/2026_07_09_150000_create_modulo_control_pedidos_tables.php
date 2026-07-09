<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_estatus_pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_interno', 50)->unique();
            $table->string('nombre_visual');
            $table->string('color_hex', 7)->default('#3B82F6');
            $table->string('fase_ciclo', 50);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('catalogo_almacenes_salida', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('catalogo_paqueterias_pedido', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('catalogo_tipos_caja_pedido', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->decimal('peso_volumetrico', 10, 4)->default(0);
            $table->string('medidas')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('catalogo_tipos_guia_pedido', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('catalogo_zonas_pedido', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('pedidos_bma', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->date('fecha');
            $table->foreignId('vendedor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('catalogo_almacen_salida_id')->nullable()->constrained('catalogo_almacenes_salida')->nullOnDelete();
            $table->foreignId('catalogo_banco_id')->nullable()->constrained('catalogo_bancos')->nullOnDelete();
            $table->boolean('requiere_factura')->default(false);
            $table->decimal('saldo_a_favor', 12, 2)->default(0);
            $table->foreignId('catalogo_tipo_caja_id')->nullable()->constrained('catalogo_tipos_caja_pedido')->nullOnDelete();
            $table->unsignedSmallInteger('numero_cajas')->default(1);
            $table->decimal('peso_real_kg', 10, 4)->nullable();
            $table->foreignId('catalogo_paqueteria_id')->nullable()->constrained('catalogo_paqueterias_pedido')->nullOnDelete();
            $table->foreignId('catalogo_tipo_guia_id')->nullable()->constrained('catalogo_tipos_guia_pedido')->nullOnDelete();
            $table->foreignId('catalogo_zona_id')->nullable()->constrained('catalogo_zonas_pedido')->nullOnDelete();
            $table->string('codigo_postal', 10)->nullable();
            $table->text('domicilio_entrega')->nullable();
            $table->string('envia_otra_persona')->nullable();
            $table->decimal('total_mercancia', 12, 2)->default(0);
            $table->decimal('costo_envio', 12, 2)->default(0);
            $table->boolean('aplica_seguro')->default(false);
            $table->decimal('costo_seguro', 12, 2)->default(0);
            $table->decimal('total_a_cobrar', 12, 2)->default(0);
            $table->foreignId('catalogo_estatus_pedido_id')->constrained('catalogo_estatus_pedidos')->restrictOnDelete();
            $table->text('comentarios_drive')->nullable();
            $table->string('numero_rastreo')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['vendedor_id', 'catalogo_estatus_pedido_id']);
            $table->index('fecha');
        });

        Schema::create('pedido_bma_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->string('ruta_archivo');
            $table->string('nombre_original');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('tamano_bytes')->default(0);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('pedido_bma_historial_estados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('estatus_anterior_id')->nullable()->constrained('catalogo_estatus_pedidos')->nullOnDelete();
            $table->foreignId('estatus_nuevo_id')->constrained('catalogo_estatus_pedidos')->restrictOnDelete();
            $table->text('comentarios')->nullable();
            $table->timestamps();
        });

        $now = now();

        $estatus = [
            ['codigo_interno' => 'BORRADOR', 'nombre_visual' => 'Borrador', 'color_hex' => '#94A3B8', 'fase_ciclo' => 'BORRADOR', 'orden' => 1],
            ['codigo_interno' => 'AZUL_1', 'nombre_visual' => 'AZUL ①', 'color_hex' => '#3B82F6', 'fase_ciclo' => 'PENDIENTE_AUXILIAR', 'orden' => 2],
            ['codigo_interno' => 'AMARILLO', 'nombre_visual' => 'AMARILLO', 'color_hex' => '#EAB308', 'fase_ciclo' => 'EN_CEDIS', 'orden' => 3],
            ['codigo_interno' => 'NARANJA', 'nombre_visual' => 'NARANJA', 'color_hex' => '#F97316', 'fase_ciclo' => 'RECHAZADO_VENDEDORA', 'orden' => 4],
            ['codigo_interno' => 'ROJO', 'nombre_visual' => 'ROJO', 'color_hex' => '#EF4444', 'fase_ciclo' => 'INCIDENCIA_CEDIS', 'orden' => 5],
            ['codigo_interno' => 'VERDE', 'nombre_visual' => 'VERDE', 'color_hex' => '#22C55E', 'fase_ciclo' => 'EN_RUTA', 'orden' => 6],
        ];

        foreach ($estatus as $row) {
            DB::table('catalogo_estatus_pedidos')->insert(array_merge($row, [
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        foreach (['PDV', 'VTA'] as $nombre) {
            DB::table('catalogo_almacenes_salida')->insert([
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (['FEDEX', 'ESTAFETA', 'TAXI FRONTERA'] as $nombre) {
            DB::table('catalogo_paqueterias_pedido')->insert([
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ([
            ['nombre' => 'CAJA #30', 'peso_volumetrico' => 5.0, 'medidas' => '30x30x30 cm'],
            ['nombre' => 'CAJA UNIVERSO GRANDE', 'peso_volumetrico' => 8.0, 'medidas' => '40x40x40 cm'],
        ] as $caja) {
            DB::table('catalogo_tipos_caja_pedido')->insert(array_merge($caja, [
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        foreach (['Terrestre', 'Express'] as $nombre) {
            DB::table('catalogo_tipos_guia_pedido')->insert([
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (['Con reexpedición', 'Sin reexpedición'] as $nombre) {
            DB::table('catalogo_zonas_pedido')->insert([
                'nombre' => $nombre,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_historial_estados');
        Schema::dropIfExists('pedido_bma_documentos');
        Schema::dropIfExists('pedidos_bma');
        Schema::dropIfExists('catalogo_zonas_pedido');
        Schema::dropIfExists('catalogo_tipos_guia_pedido');
        Schema::dropIfExists('catalogo_tipos_caja_pedido');
        Schema::dropIfExists('catalogo_paqueterias_pedido');
        Schema::dropIfExists('catalogo_almacenes_salida');
        Schema::dropIfExists('catalogo_estatus_pedidos');
    }
};
