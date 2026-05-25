<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->string('motivo_incorrecta')->nullable()->after('catalogo_estado_solicitud_id');
            $table->timestamp('rollback_confirmado_at')->nullable()->after('motivo_incorrecta');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropColumn(['motivo_incorrecta', 'rollback_confirmado_at']);
        });
    }
};
