<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULTS = [
        'meli_full_pct_1' => '14',
        'meli_full_pct_2' => '8',
        'meli_msi_pct_1' => '17.5',
        'meli_msi_pct_2' => '8',
        'meli_pct_iva' => '2.5',
        'meli_factor_iva' => '1.16',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('gelia_settings')) {
            return;
        }

        foreach (self::DEFAULTS as $key => $value) {
            $exists = DB::table('gelia_settings')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }

            DB::table('gelia_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('gelia_settings')) {
            return;
        }

        DB::table('gelia_settings')->whereIn('key', array_keys(self::DEFAULTS))->delete();
    }
};
