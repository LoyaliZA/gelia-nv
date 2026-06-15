<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reemplaza los 3 escalones simplificados por los 9 rangos del sistema legacy.
     */
    public function up(): void
    {
        DB::table('woocommerce_margins')->truncate();

        $now = now();

        DB::table('woocommerce_margins')->insert([
            ['precio_min' => 0, 'precio_max' => 100.00, 'multiplicador_rebaja' => 1.70, 'multiplicador_normal' => 1.80, 'created_at' => $now, 'updated_at' => $now],
            ['precio_min' => 100.01, 'precio_max' => 129.00, 'multiplicador_rebaja' => 1.60, 'multiplicador_normal' => 1.78, 'created_at' => $now, 'updated_at' => $now],
            ['precio_min' => 129.01, 'precio_max' => 190.00, 'multiplicador_rebaja' => 1.55, 'multiplicador_normal' => 1.73, 'created_at' => $now, 'updated_at' => $now],
            ['precio_min' => 190.01, 'precio_max' => 280.00, 'multiplicador_rebaja' => 1.50, 'multiplicador_normal' => 1.63, 'created_at' => $now, 'updated_at' => $now],
            ['precio_min' => 280.01, 'precio_max' => 359.00, 'multiplicador_rebaja' => 1.45, 'multiplicador_normal' => 1.55, 'created_at' => $now, 'updated_at' => $now],
            ['precio_min' => 359.01, 'precio_max' => 399.00, 'multiplicador_rebaja' => 1.35, 'multiplicador_normal' => 1.41, 'created_at' => $now, 'updated_at' => $now],
            ['precio_min' => 399.01, 'precio_max' => 699.00, 'multiplicador_rebaja' => 1.30, 'multiplicador_normal' => 1.35, 'created_at' => $now, 'updated_at' => $now],
            ['precio_min' => 699.01, 'precio_max' => 999.00, 'multiplicador_rebaja' => 1.25, 'multiplicador_normal' => 1.28, 'created_at' => $now, 'updated_at' => $now],
            ['precio_min' => 999.01, 'precio_max' => 99999.00, 'multiplicador_rebaja' => 1.20, 'multiplicador_normal' => 1.22, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('woocommerce_margins')->truncate();

        $now = now();

        DB::table('woocommerce_margins')->insert([
            ['precio_min' => 0, 'precio_max' => 99.99, 'multiplicador_rebaja' => 1.70, 'multiplicador_normal' => 1.80, 'created_at' => $now, 'updated_at' => $now],
            ['precio_min' => 100, 'precio_max' => 199.99, 'multiplicador_rebaja' => 1.65, 'multiplicador_normal' => 1.75, 'created_at' => $now, 'updated_at' => $now],
            ['precio_min' => 200, 'precio_max' => 99999, 'multiplicador_rebaja' => 1.60, 'multiplicador_normal' => 1.70, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
