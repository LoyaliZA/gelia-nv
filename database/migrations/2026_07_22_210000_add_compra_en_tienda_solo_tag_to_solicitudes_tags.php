<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->boolean('compra_en_tienda_solo_tag')
                ->default(false)
                ->after('compra_en_tienda');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_tags', function (Blueprint $table) {
            $table->dropColumn('compra_en_tienda_solo_tag');
        });
    }
};
