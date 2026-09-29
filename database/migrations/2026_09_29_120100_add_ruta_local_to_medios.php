<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medios', function (Blueprint $table) {
            $table->string('ruta_local')->nullable()->after('object_key');
        });
    }

    public function down(): void
    {
        Schema::table('medios', function (Blueprint $table) {
            $table->dropColumn('ruta_local');
        });
    }
};
