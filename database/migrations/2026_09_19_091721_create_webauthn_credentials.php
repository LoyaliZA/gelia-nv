<?php

use Illuminate\Database\Schema\Blueprint;
use Laragear\WebAuthn\Models\WebAuthnCredential;

return WebAuthnCredential::migration()->with(function (Blueprint $table) {
    $table->string('nickname', 120)->nullable();
    $table->string('platform', 32)->nullable();
    $table->foreignId('mobile_device_id')->nullable()->constrained('mobile_devices')->nullOnDelete();
    $table->timestamp('last_used_at')->nullable();
    $table->timestamp('revocado_at')->nullable();

    $table->index(['authenticatable_id', 'revocado_at']);
    $table->index('mobile_device_id');
});
