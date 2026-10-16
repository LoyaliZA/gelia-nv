<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_bma_pagos', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->after('pedido_bma_id');
            $table->unique(['pedido_bma_id', 'idempotency_key'], 'pedido_bma_pagos_idempotencia_unica');
        });
    }

    public function down(): void
    {
        Schema::table('pedido_bma_pagos', function (Blueprint $table) {
            $table->dropUnique('pedido_bma_pagos_idempotencia_unica');
            $table->dropColumn('idempotency_key');
        });
    }
};
