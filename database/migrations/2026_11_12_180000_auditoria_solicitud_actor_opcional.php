<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias_solicitudes', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->foreignId('usuario_id')->nullable()->change();
            $table->foreign('usuario_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        if (DB::table('auditorias_solicitudes')->whereNull('usuario_id')->exists()) {
            throw new \RuntimeException('Hay auditorías sin actor. No se vuelve a exigir un usuario.');
        }

        Schema::table('auditorias_solicitudes', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->foreignId('usuario_id')->nullable(false)->change();
            $table->foreign('usuario_id')->references('id')->on('users');
        });
    }
};
