<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_sync_publications', function (Blueprint $table) {
            $table->id('seq');
            $table->uuid('batch_id')->nullable()->index();
            $table->string('aggregate_type', 32);
            $table->unsignedBigInteger('aggregate_id');
            $table->string('operation', 16);
            $table->json('grant_user_ids');
            $table->json('revoke_user_ids');
            $table->json('scope_before')->nullable();
            $table->json('scope_after')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['aggregate_type', 'aggregate_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_sync_publications');
    }
};
