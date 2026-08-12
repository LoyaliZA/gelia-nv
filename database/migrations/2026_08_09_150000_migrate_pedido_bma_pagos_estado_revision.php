<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('pedido_bma_pagos')
            ->where('estado_revision', 'confirmado')
            ->update(['estado_revision' => 'verificado']);

        DB::table('pedido_bma_pagos')
            ->where('estado_revision', 'con_diferencia')
            ->update(['estado_revision' => 'con_observaciones']);
    }

    public function down(): void
    {
        DB::table('pedido_bma_pagos')
            ->where('estado_revision', 'verificado')
            ->update(['estado_revision' => 'confirmado']);

        DB::table('pedido_bma_pagos')
            ->where('estado_revision', 'con_observaciones')
            ->update(['estado_revision' => 'con_diferencia']);
    }
};
