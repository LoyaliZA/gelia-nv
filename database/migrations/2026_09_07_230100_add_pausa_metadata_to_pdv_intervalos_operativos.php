<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_intervalos_operativos', function (Blueprint $table) {
            $table->foreignId('motivo_pausa_id')
                ->nullable()
                ->after('tipo')
                ->constrained('pdv_motivos_pausa')
                ->nullOnDelete();
            $table->string('motivo_detalle', 500)->nullable()->after('motivo_pausa_id');
            $table->foreignId('pausa_iniciada_por_id')
                ->nullable()
                ->after('motivo_detalle')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('pausa_finalizada_por_id')
                ->nullable()
                ->after('pausa_iniciada_por_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdv_intervalos_operativos', function (Blueprint $table) {
            $table->dropForeign(['motivo_pausa_id']);
            $table->dropForeign(['pausa_iniciada_por_id']);
            $table->dropForeign(['pausa_finalizada_por_id']);
            $table->dropColumn([
                'motivo_pausa_id',
                'motivo_detalle',
                'pausa_iniciada_por_id',
                'pausa_finalizada_por_id',
            ]);
        });
    }
};
