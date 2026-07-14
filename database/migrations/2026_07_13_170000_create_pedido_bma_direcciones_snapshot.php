<?php

use App\Services\Permisos\PermisoCatalogoMigracion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISOS = [
        'control_pedidos.direccion.seleccionar',
        'control_pedidos.direccion.cambiar',
        'control_pedidos.direccion.cambiar_despues_remision',
        'control_pedidos.direccion.cambiar_despues_guia',
        'control_pedidos.direccion.usar_manual',
    ];

    public function up(): void
    {
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->foreignId('cliente_direccion_id')
                ->nullable()
                ->after('cliente_id')
                ->constrained('cliente_direcciones')
                ->nullOnDelete();
        });

        Schema::create('pedido_bma_direcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->foreignId('cliente_direccion_id')->nullable()->constrained('cliente_direcciones')->nullOnDelete();
            $table->unsignedInteger('version_snapshot')->default(1);
            $table->boolean('es_vigente')->default(true);
            $table->string('motivo_cambio')->nullable();
            $table->foreignId('cambiado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cambiado_en')->nullable();
            $table->unsignedInteger('numero_direccion')->nullable();
            $table->string('etiqueta')->nullable();
            $table->string('tipo_direccion', 50)->nullable();
            $table->string('nombre_destinatario');
            $table->string('telefono_destinatario', 30)->nullable();
            $table->string('calle')->nullable();
            $table->string('numero_exterior', 30)->nullable();
            $table->string('numero_interior', 30)->nullable();
            $table->string('colonia')->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('municipio')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('estado')->nullable();
            $table->string('pais')->nullable();
            $table->text('referencias')->nullable();
            $table->text('indicaciones_entrega')->nullable();
            $table->text('domicilio_legacy')->nullable();
            $table->string('origen', 40)->default('normalizado');
            $table->timestamps();

            $table->index(['pedido_bma_id', 'es_vigente']);
        });

        PermisoCatalogoMigracion::registrar(self::PERMISOS);
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_direcciones');
        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cliente_direccion_id');
        });
        \Spatie\Permission\Models\Permission::whereIn('name', self::PERMISOS)->delete();
    }
};
