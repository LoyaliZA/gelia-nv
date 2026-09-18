<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_bootstrap_snapshot_items', function (Blueprint $table) {
            $table->uuid('snapshot_id');
            $table->unsignedBigInteger('cliente_id');
            $table->json('payload');

            $table->primary(['snapshot_id', 'cliente_id']);
            $table->foreign('snapshot_id')
                ->references('id')
                ->on('mobile_bootstrap_snapshots')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_bootstrap_snapshot_items');
    }
};
