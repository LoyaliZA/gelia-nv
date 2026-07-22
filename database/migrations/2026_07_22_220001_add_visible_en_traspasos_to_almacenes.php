<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('almacenes', function (Blueprint $table) {
            $table->boolean('visible_en_traspasos')->default(false)->after('visible_en_pedidos');
        });

        DB::table('almacenes')
            ->where('codigo', 'like', '%CEDIS%')
            ->orWhere('nombre', 'like', '%CEDIS%')
            ->update(['visible_en_traspasos' => true]);
    }

    public function down(): void
    {
        Schema::table('almacenes', function (Blueprint $table) {
            $table->dropColumn('visible_en_traspasos');
        });
    }
};
