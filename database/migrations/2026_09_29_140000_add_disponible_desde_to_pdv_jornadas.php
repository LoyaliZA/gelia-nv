<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_jornadas', function (Blueprint $table) {
            $table->timestamp('disponible_desde')->nullable()->after('cierre_at');
        });
    }

    public function down(): void
    {
        Schema::table('pdv_jornadas', function (Blueprint $table) {
            $table->dropColumn('disponible_desde');
        });
    }
};
