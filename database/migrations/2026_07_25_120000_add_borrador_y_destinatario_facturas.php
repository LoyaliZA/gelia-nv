<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('catalogo_estados_solicitud')->updateOrInsert(
            ['nombre' => 'Borrador'],
            [
                'descripcion' => 'Solicitud de factura en borrador, esperando formulario o envío a encargada',
                'activo' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        if (class_exists(\App\Models\CatalogoEstadoSolicitud::class)) {
            \App\Models\CatalogoEstadoSolicitud::reiniciarCache();
        }

        Schema::table('solicitudes_facturas', function (Blueprint $table) {
            $table->string('destinatario_tipo', 20)->default('cliente')->after('cliente_id');
            $table->json('campos_fiscales_solicitados')->nullable()->after('datos_fiscales');
            $table->timestamp('formulario_enviado_at')->nullable()->after('campos_fiscales_solicitados');
            $table->timestamp('formulario_respondido_at')->nullable()->after('formulario_enviado_at');
        });

        Schema::create('enlaces_datos_fiscales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_factura_id')->constrained('solicitudes_facturas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('codigo_publico', 64)->unique();
            $table->string('accion_permitida', 40);
            $table->json('campos_permitidos')->nullable();
            $table->string('destinatario_tipo', 20)->default('cliente');
            $table->timestamp('expira_en')->nullable();
            $table->timestamp('usado_en')->nullable();
            $table->timestamp('revocado_en')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['solicitud_factura_id', 'revocado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enlaces_datos_fiscales');

        Schema::table('solicitudes_facturas', function (Blueprint $table) {
            $table->dropColumn([
                'destinatario_tipo',
                'campos_fiscales_solicitados',
                'formulario_enviado_at',
                'formulario_respondido_at',
            ]);
        });

        DB::table('catalogo_estados_solicitud')->where('nombre', 'Borrador')->delete();
    }
};
