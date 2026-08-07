<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saf_impresion_preferencias', function (Blueprint $table) {
            $table->string('sucursal', 128)->nullable()->after('copias');
            $table->string('caja', 64)->nullable()->after('sucursal');
        });
    }

    public function down(): void
    {
        Schema::table('saf_impresion_preferencias', function (Blueprint $table) {
            $table->dropColumn(['sucursal', 'caja']);
        });
    }
};
