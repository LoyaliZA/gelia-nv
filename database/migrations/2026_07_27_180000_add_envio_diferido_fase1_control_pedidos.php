<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalogo_tipos_operacion_envio', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre');
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('catalogo_tipos_operacion_envio')->insert([
            [
                'codigo' => 'NORMAL',
                'nombre' => 'Pedido normal',
                'descripcion' => 'Cobro y peso de envío al registrar el pedido.',
                'activo' => true,
                'orden' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'codigo' => 'MUNICIPIO_DIFERIDO',
                'nombre' => 'Municipio con envío diferido',
                'descripcion' => 'Registra sin costo de envío definitivo; se anexa después.',
                'activo' => true,
                'orden' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $normalId = DB::table('catalogo_tipos_operacion_envio')->where('codigo', 'NORMAL')->value('id');

        Schema::table('pedidos_bma', function (Blueprint $table) use ($normalId) {
            $table->foreignId('tipo_operacion_envio_id')
                ->nullable()
                ->after('origen_id')
                ->constrained('catalogo_tipos_operacion_envio')
                ->nullOnDelete();
            $table->string('estatus_envio', 40)->default('completo')->after('costo_envio');
        });

        DB::table('pedidos_bma')->update([
            'tipo_operacion_envio_id' => $normalId,
            'estatus_envio' => 'completo',
        ]);

        Schema::create('pedido_bma_anexos_envio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_bma_id')->constrained('pedidos_bma')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->foreignId('catalogo_banco_id')->nullable()->constrained('catalogo_bancos')->nullOnDelete();
            $table->text('comentarios')->nullable();
            $table->string('ruta_archivo');
            $table->string('nombre_original')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('tamano_bytes')->nullable();
            $table->string('estatus', 20)->default('pendiente');
            $table->text('motivo_rechazo')->nullable();
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validado_at')->nullable();
            $table->timestamps();

            $table->index(['pedido_bma_id', 'estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_bma_anexos_envio');

        Schema::table('pedidos_bma', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_operacion_envio_id');
            $table->dropColumn('estatus_envio');
        });

        Schema::dropIfExists('catalogo_tipos_operacion_envio');
    }
};
