<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_user_sync_state', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_device_id')->constrained('mobile_devices')->cascadeOnDelete();
            $table->string('scope_version', 64);
            $table->unsignedBigInteger('cursor_seq')->default(0);
            $table->uuid('bootstrap_snapshot_id')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique('mobile_device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_user_sync_state');
    }
};
