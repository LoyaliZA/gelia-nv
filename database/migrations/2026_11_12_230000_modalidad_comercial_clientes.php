<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('clientes', 'modalidad_comercial')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->string('modalidad_comercial', 20)->nullable()->after('es_inactivo');
                $table->string('modalidad_origen', 40)->nullable()->after('modalidad_comercial');
                $table->foreignId('conversion_solicitud_id')->nullable()->after('modalidad_origen')->constrained('solicitudes_tags')->nullOnDelete();
                $table->foreignId('conversion_usuario_id')->nullable()->after('conversion_solicitud_id')->constrained('users')->nullOnDelete();
                $table->timestamp('conversion_autorizada_at')->nullable()->after('conversion_usuario_id');
            });
        }

        if (! Schema::hasTable('cliente_modalidad_eventos')) {
            Schema::create('cliente_modalidad_eventos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
                $table->string('modalidad', 20)->nullable();
                $table->string('origen', 40);
                $table->foreignId('solicitud_id')->nullable()->constrained('solicitudes_tags')->nullOnDelete();
                $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['cliente_id', 'created_at']);
            });
        }

        if (! Schema::hasColumn('solicitudes_tags', 'convertir_menudeo_mayoreo')) {
            Schema::table('solicitudes_tags', function (Blueprint $table) {
                $table->boolean('convertir_menudeo_mayoreo')->default(false);
            });
        }

        $pg = DB::table('catalogo_listas_descuento')->where('nombre', 'PUBLICO GENERAL')->value('id');
        if (! $pg) {
            return;
        }

        DB::table('clientes')
            ->where('lista_actual_id', $pg)
            ->where('monto_venta_actual', '>', 0)
            ->where('es_inactivo', false)
            ->where(function ($query) {
                $query->where('lista_bloqueada', false)->orWhereNull('lista_bloqueada');
            })
            ->whereNull('modalidad_comercial')
            ->update([
                'modalidad_comercial' => 'menudeo',
                'modalidad_origen' => 'migracion_conservadora',
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_modalidad_eventos');

        if (Schema::hasColumn('solicitudes_tags', 'convertir_menudeo_mayoreo')) {
            Schema::table('solicitudes_tags', function (Blueprint $table) {
                $table->dropColumn('convertir_menudeo_mayoreo');
            });
        }

        if (Schema::hasColumn('clientes', 'modalidad_comercial')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropConstrainedForeignId('conversion_solicitud_id');
                $table->dropConstrainedForeignId('conversion_usuario_id');
                $table->dropColumn(['modalidad_comercial', 'modalidad_origen', 'conversion_autorizada_at']);
            });
        }
    }
};
