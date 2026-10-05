<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visita_cliente_programadas', function (Blueprint $table) {
            $table->boolean('es_demo')->default(false)->after('idempotency_key');
            $table->index('es_demo');
        });
    }

    public function down(): void
    {
        Schema::table('visita_cliente_programadas', function (Blueprint $table) {
            $table->dropIndex(['es_demo']);
            $table->dropColumn('es_demo');
        });
    }
};
