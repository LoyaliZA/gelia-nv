<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('importaciones_clientes', function (Blueprint $table) {
            $table->unsignedInteger('descensos')->default(0)->after('ascensos');
        });
    }

    public function down(): void
    {
        Schema::table('importaciones_clientes', function (Blueprint $table) {
            $table->dropColumn('descensos');
        });
    }
};
