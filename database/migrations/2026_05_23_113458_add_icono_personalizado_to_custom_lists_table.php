<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('custom_lists', function (Blueprint $table) {
        $table->string('icono_personalizado')->default('FileSpreadsheet')->after('color');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_lists', function (Blueprint $table) {
            //
        });
    }
};
