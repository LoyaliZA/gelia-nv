<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('rh_configuraciones')) {
            return;
        }

        Schema::table('rh_configuraciones', function (Blueprint $table) {
            if (!Schema::hasColumn('rh_configuraciones', 'periodo_cerrado_en')) {
                $table->dateTime('periodo_cerrado_en')->nullable()->after('periodo_actual_fin');
            }
            if (!Schema::hasColumn('rh_configuraciones', 'periodo_cerrado_por_id')) {
                $table->foreignId('periodo_cerrado_por_id')->nullable()->after('periodo_cerrado_en')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('rh_configuraciones', 'periodo_cerrado_inicio')) {
                $table->date('periodo_cerrado_inicio')->nullable()->after('periodo_cerrado_por_id');
            }
            if (!Schema::hasColumn('rh_configuraciones', 'periodo_cerrado_fin')) {
                $table->date('periodo_cerrado_fin')->nullable()->after('periodo_cerrado_inicio');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('rh_configuraciones')) {
            return;
        }

        Schema::table('rh_configuraciones', function (Blueprint $table) {
            if (Schema::hasColumn('rh_configuraciones', 'periodo_cerrado_por_id')) {
                $table->dropForeign(['periodo_cerrado_por_id']);
            }
            $cols = ['periodo_cerrado_en', 'periodo_cerrado_por_id', 'periodo_cerrado_inicio', 'periodo_cerrado_fin'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('rh_configuraciones', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
