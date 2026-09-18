<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_bootstrap_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope_version', 64);
            $table->unsignedBigInteger('publish_seq_at_start')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedBigInteger('max_cliente_id')->nullable();
            $table->string('status', 16);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_bootstrap_snapshots');
    }
};
