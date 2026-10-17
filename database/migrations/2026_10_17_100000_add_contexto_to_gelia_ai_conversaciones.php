<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('gelia_ai_conversaciones', 'contexto')) {
            return;
        }

        Schema::table('gelia_ai_conversaciones', function (Blueprint $table) {
            $table->json('contexto')->nullable()->after('temporal');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('gelia_ai_conversaciones', 'contexto')) {
            Schema::table('gelia_ai_conversaciones', function (Blueprint $table) {
                $table->dropColumn('contexto');
            });
        }
    }
};
