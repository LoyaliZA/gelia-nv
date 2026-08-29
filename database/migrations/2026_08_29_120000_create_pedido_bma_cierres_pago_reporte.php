<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_bma_cierres_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('estado', 20)->default('vigente');
            $table->string('origen', 20)->default('flujo');
            $table->date('pedido_fecha')->nullable();
            $table->timestamp('validado_at');
            $table->foreignId('validado_por_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('revocado_at')->nullable();
            $table->foreignId('revocado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo_revocacion')->nullable();
            $table->decimal('monto_venta', 12, 2);
            $table->decimal('monto_envio', 12, 2)->default(0);
            $table->decimal('monto_seguro', 12, 2)->default(0);
            $table->decimal('total_pedido', 12, 2);
            $table->decimal('saf_aplicado', 12, 2)->default(0);
            $table->decimal('total_a_cobrar', 12, 2);
            $table->decimal('pagos_validos', 12, 2);
            $table->decimal('diferencia', 12, 2);
            $table->decimal('excedente', 12, 2)->default(0);
            $table->decimal('tolerancia_aplicada', 8, 2);
            $table->string('estado_cobertura', 30);
            $table->string('folio_snapshot')->nullable();
            $table->string('folio_remision_snapshot')->nullable();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->foreignId('almacen_id')->nullable()->constrained('almacenes')->nullOnDelete();
            $table->json('metadata_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['pedido_bma_id', 'version'], 'pedido_bma_cierres_pago_pedido_version_unique');
            $table->index(['validado_at', 'estado'], 'pedido_bma_cierres_pago_validado_estado_idx');
            $table->index(['departamento_id', 'validado_at'], 'pedido_bma_cierres_pago_depto_validado_idx');
            $table->index(['vendedor_id', 'validado_at'], 'pedido_bma_cierres_pago_vendedor_validado_idx');
            $table->index(['almacen_id', 'validado_at'], 'pedido_bma_cierres_pago_almacen_validado_idx');
        });

        Schema::create('pedido_bma_cierre_pago_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_cierre_pago_id')->constrained('pedido_bma_cierres_pago')->cascadeOnDelete();
            $table->foreignId('pedido_bma_pago_id')->constrained('pedido_bma_pagos')->restrictOnDelete();
            $table->unsignedSmallInteger('numero_exhibicion');
            $table->decimal('monto_snapshot', 12, 2);
            $table->string('forma_pago_snapshot', 30)->nullable();
            $table->foreignId('catalogo_banco_id')->nullable()->constrained('catalogo_bancos')->nullOnDelete();
            $table->string('banco_snapshot')->nullable();
            $table->string('referencia_snapshot')->nullable();
            $table->timestamp('fecha_pago_snapshot')->nullable();
            $table->string('estado_revision_snapshot', 30);
            $table->boolean('activo_para_cobertura_snapshot')->default(true);
            $table->string('nombre_archivo_snapshot')->nullable();
            $table->string('mime_type_snapshot')->nullable();
            $table->unsignedBigInteger('tamano_bytes_snapshot')->nullable();
            $table->string('ruta_archivo_snapshot')->nullable();
            $table->foreignId('reemplaza_pago_id')->nullable()->constrained('pedido_bma_pagos')->nullOnDelete();
            $table->foreignId('capturado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('capturado_at_snapshot')->nullable();
            $table->foreignId('revisado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_at_snapshot')->nullable();
            $table->text('motivo_rechazo_snapshot')->nullable();
            $table->timestamps();

            $table->index('pedido_bma_cierre_pago_id', 'pedido_bma_cierre_items_cierre_idx');
        });

        Schema::table('pedido_bma_documentos', function (Blueprint $table) {
            $table->foreignId('reemplaza_documento_id')
                ->nullable()
                ->after('pedido_bma_id')
                ->constrained('pedido_bma_documentos')
                ->nullOnDelete();
            $table->boolean('activo')->default(true)->after('relacion_id');
            $table->timestamp('sustituido_at')->nullable()->after('activo');
            $table->foreignId('sustituido_por_id')->nullable()->after('sustituido_at')->constrained('users')->nullOnDelete();
        });

        PermisoCatalogoMigracion::registrar([
            'reportes.pagos_pedidos.ver',
            'reportes.pagos_pedidos.ver_evidencias',
            'reportes.pagos_pedidos.exportar_csv',
            'reportes.pagos_pedidos.exportar_pdf',
            'reportes.pagos_pedidos.ver_historico',
        ]);
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'reportes.pagos_pedidos.ver',
            'reportes.pagos_pedidos.ver_evidencias',
            'reportes.pagos_pedidos.exportar_csv',
            'reportes.pagos_pedidos.exportar_pdf',
            'reportes.pagos_pedidos.ver_historico',
        ])->delete();

        Schema::table('pedido_bma_documentos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sustituido_por_id');
            $table->dropColumn(['sustituido_at', 'activo']);
            $table->dropConstrainedForeignId('reemplaza_documento_id');
        });

        Schema::dropIfExists('pedido_bma_cierre_pago_items');
        Schema::dropIfExists('pedido_bma_cierres_pago');
    }
};
