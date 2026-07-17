<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULTS = [
        'meli_factor_base' => '1.1',
        'meli_full_multiplicador' => '1.13',
        'meli_full_fijo_1' => '45',
        'meli_full_fijo_2' => '90',
        'meli_msi_multiplicador' => '1.175',
        'meli_msi_fijo_1' => '90',
        'meli_msi_fijo_2' => '90',
        'pct_boutique' => '25',
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
