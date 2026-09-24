<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->boolean('visible_origen_resguardo_pdv')->default(false)->after('activo');
        });

        DB::table('departamentos')
            ->where(function ($query): void {
                $query->whereIn('nombre', ['Aromas', 'Bellaroma'])
                    ->orWhere('nombre', 'like', 'Aromas %')
                    ->orWhere('nombre', 'like', 'Bellaroma %');
            })
            ->update(['visible_origen_resguardo_pdv' => true]);
    }

    public function down(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropColumn('visible_origen_resguardo_pdv');
        });
    }
};
