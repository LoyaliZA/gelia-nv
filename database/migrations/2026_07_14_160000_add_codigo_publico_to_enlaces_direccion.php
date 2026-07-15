<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enlaces_direccion', function (Blueprint $table) {
            $table->string('codigo_publico', 16)->nullable()->unique()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('enlaces_direccion', function (Blueprint $table) {
            $table->dropUnique(['codigo_publico']);
            $table->dropColumn('codigo_publico');
        });
    }
};
