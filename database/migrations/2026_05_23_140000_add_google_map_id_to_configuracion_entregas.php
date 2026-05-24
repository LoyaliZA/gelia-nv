<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_entregas', function (Blueprint $table) {
            $table->string('google_map_id', 128)->nullable()->after('api_key_google');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_entregas', function (Blueprint $table) {
            $table->dropColumn('google_map_id');
        });
    }
};
