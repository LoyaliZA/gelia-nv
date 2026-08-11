<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_image_imports', function (Blueprint $table) {
            $table->boolean('reemplazar_primera')->default(true)->after('mensaje_error');
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_image_imports', function (Blueprint $table) {
            $table->dropColumn('reemplazar_primera');
        });
    }
};
