<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listado_generados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipo_lista');
            $table->foreignId('custom_list_id')->nullable()->constrained('custom_lists')->nullOnDelete();
            $table->string('nombre_archivo');
            $table->string('ruta_fisica');
            $table->string('tamano_kb')->nullable();
            $table->boolean('enviado_correo')->default(false);
            $table->timestamps();

            $table->index(['tipo_lista', 'created_at']);
        });

        Schema::create('listado_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_lista');
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('nombre')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();

            $table->unique(['tipo_lista', 'user_id'], 'uq_listado_dest_tipo_user');
            $table->unique(['tipo_lista', 'email'], 'uq_listado_dest_tipo_email');
            $table->index('tipo_lista');
        });

        Schema::table('custom_lists', function (Blueprint $table) {
            $table->json('destinatarios_user_ids')->nullable()->after('pct_venta_especial');
            $table->json('destinatarios_externos')->nullable()->after('destinatarios_user_ids');
        });
    }

    public function down(): void
    {
        Schema::table('custom_lists', function (Blueprint $table) {
            $table->dropColumn(['destinatarios_user_ids', 'destinatarios_externos']);
        });

        Schema::dropIfExists('listado_destinatarios');
        Schema::dropIfExists('listado_generados');
    }
};
