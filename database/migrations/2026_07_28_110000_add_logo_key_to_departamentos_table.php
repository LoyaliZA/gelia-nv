<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->string('logo_key', 64)->nullable()->after('codigo');
        });

        $rows = DB::table('departamentos')->select('id', 'nombre')->get();
        foreach ($rows as $row) {
            $nombre = Str::lower(trim((string) $row->nombre));
            $key = (str_contains($nombre, 'bellaroma') || $nombre === 'bellaroma')
                ? 'bellaroma_logo_negro'
                : 'aromas_logo_negro';
            DB::table('departamentos')->where('id', $row->id)->update(['logo_key' => $key]);
        }
    }

    public function down(): void
    {
        Schema::table('departamentos', function (Blueprint $table) {
            $table->dropColumn('logo_key');
        });
    }
};
