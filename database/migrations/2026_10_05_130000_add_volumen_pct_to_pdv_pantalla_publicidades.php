<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pdv_pantalla_publicidades', function (Blueprint $table): void {
            $table->unsignedTinyInteger('volumen_pct')->nullable()->after('ajuste');
        });
    }

    public function down(): void
    {
        Schema::table('pdv_pantalla_publicidades', function (Blueprint $table): void {
            $table->dropColumn('volumen_pct');
        });
    }
};
