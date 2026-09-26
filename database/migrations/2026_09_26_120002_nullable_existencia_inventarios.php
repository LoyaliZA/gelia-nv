<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            $table->decimal('existencia', 12, 3)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventarios', function (Blueprint $table) {
            $table->decimal('existencia', 12, 3)->default(0)->change();
        });
    }
};
