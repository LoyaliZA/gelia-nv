<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_image_import_items', function (Blueprint $table) {
            $table->string('motivo')->nullable()->after('estado')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_image_import_items', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }
};
