<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_terminal_alertas_sucursal', function (Blueprint $table) {
            $table->string('proposito', 24)->default('alertas')->after('terminal_id');
            $table->dropUnique('pdv_terminal_alertas_terminal_unique');
            $table->unique(['terminal_id', 'proposito'], 'pdv_terminal_alertas_terminal_proposito_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pdv_terminal_alertas_sucursal', function (Blueprint $table) {
            $table->dropUnique('pdv_terminal_alertas_terminal_proposito_unique');
            $table->unique('terminal_id', 'pdv_terminal_alertas_terminal_unique');
            $table->dropColumn('proposito');
        });
    }
};
