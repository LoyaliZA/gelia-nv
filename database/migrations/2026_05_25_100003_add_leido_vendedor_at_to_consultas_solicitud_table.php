<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas_solicitud', function (Blueprint $table) {
            $table->timestamp('leido_vendedor_at')->nullable()->after('encargada_id');
        });
    }

    public function down(): void
    {
        Schema::table('consultas_solicitud', function (Blueprint $table) {
            $table->dropColumn('leido_vendedor_at');
        });
    }
};
