<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_operacion_gestion_auditoria', function (Blueprint $table) {
            $table->json('contexto')->nullable()->after('estado_nuevo');
        });
    }

    public function down(): void
    {
        Schema::table('pdv_operacion_gestion_auditoria', function (Blueprint $table) {
            $table->dropColumn('contexto');
        });
    }
};
