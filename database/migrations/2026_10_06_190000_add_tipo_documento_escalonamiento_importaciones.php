<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('escalonamiento_importaciones')
            && ! Schema::hasColumn('escalonamiento_importaciones', 'tipo_documento')) {
            Schema::table('escalonamiento_importaciones', function (Blueprint $table) {
                $table->string('tipo_documento', 16)->nullable()->after('nombre_archivo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('escalonamiento_importaciones', 'tipo_documento')) {
            Schema::table('escalonamiento_importaciones', function (Blueprint $table) {
                $table->dropColumn('tipo_documento');
            });
        }
    }
};
