<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_sucursal_dias', function (Blueprint $table) {
            $table->timestamp('apertura_manual_at')->nullable()->after('acepta_altas');
            $table->foreignId('apertura_manual_por_id')
                ->nullable()
                ->after('apertura_manual_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pdv_sucursal_dias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('apertura_manual_por_id');
            $table->dropColumn('apertura_manual_at');
        });
    }
};
