<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comisiones_empaque_repartos', function (Blueprint $table) {
            $table->string('nombre')->nullable();
            $table->text('motivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('comisiones_empaque_repartos', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'motivo']);
        });
    }
};
