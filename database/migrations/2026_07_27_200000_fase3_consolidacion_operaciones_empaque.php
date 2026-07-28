<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('catalogo_tipos_operacion_envio')
            ->where('codigo', 'RESGUARDO_COMPLEMENTARIO')
            ->exists();

        if (!$exists) {
            $now = now();
            DB::table('catalogo_tipos_operacion_envio')->insert([
                'codigo' => 'RESGUARDO_COMPLEMENTARIO',
                'nombre' => 'Resguardo complementario',
                'descripcion' => 'Envío completo; puede consolidarse con otros folios del mismo cliente en empaque.',
                'activo' => true,
                'orden' => 4,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('operaciones_empaque', function (Blueprint $table) {
            $table->id();
            $table->string('folio_operacion', 40)->unique();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->unsignedInteger('numero_cajas')->nullable();
            $table->decimal('peso_real_kg', 12, 4)->nullable();
            $table->timestamp('empacado_at')->nullable();
            $table->foreignId('empacado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('estatus', 20)->default('abierta');
            $table->timestamps();

            $table->index(['cliente_id', 'estatus']);
        });

        Schema::create('operacion_empaque_miembros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operacion_empaque_id')->constrained('operaciones_empaque')->cascadeOnDelete();
            $table->foreignId('pedido_bma_id')->unique()->constrained('pedidos_bma')->restrictOnDelete();
            $table->boolean('es_principal')->default(false);
            $table->unsignedInteger('cantidad_piezas')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index('operacion_empaque_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operacion_empaque_miembros');
        Schema::dropIfExists('operaciones_empaque');

        DB::table('catalogo_tipos_operacion_envio')
            ->where('codigo', 'RESGUARDO_COMPLEMENTARIO')
            ->delete();
    }
};
