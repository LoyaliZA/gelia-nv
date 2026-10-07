<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('escalonamiento_incidencias', 'contexto')) {
            Schema::table('escalonamiento_incidencias', function (Blueprint $table) {
                $table->json('contexto')->nullable()->after('motivo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('escalonamiento_incidencias', 'contexto')) {
            Schema::table('escalonamiento_incidencias', function (Blueprint $table) {
                $table->dropColumn('contexto');
            });
        }
    }
};
