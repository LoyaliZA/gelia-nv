<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiendanube_webhook_deliveries', function (Blueprint $table) {
            $table->dropUnique(['payload_hash']);
            $table->index('payload_hash');

            $table->unsignedSmallInteger('attempts')->default(0)->after('status');
            $table->timestamp('next_attempt_at')->nullable()->index()->after('attempts');
            $table->uuid('lease_token')->nullable()->after('next_attempt_at');
            $table->timestamp('lease_expires_at')->nullable()->index()->after('lease_token');
            $table->timestamp('processing_started_at')->nullable()->after('lease_expires_at');
            $table->timestamp('processed_at')->nullable()->after('processing_started_at');
            $table->timestamp('failed_at')->nullable()->after('processed_at');
            $table->foreignId('retried_by_user_id')->nullable()->after('failed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('retried_at')->nullable()->after('retried_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('tiendanube_webhook_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retried_by_user_id');
            $table->dropColumn([
                'attempts',
                'next_attempt_at',
                'lease_token',
                'lease_expires_at',
                'processing_started_at',
                'processed_at',
                'failed_at',
                'retried_at',
            ]);
            $table->dropIndex(['payload_hash']);
            $table->unique('payload_hash');
        });
    }
};
