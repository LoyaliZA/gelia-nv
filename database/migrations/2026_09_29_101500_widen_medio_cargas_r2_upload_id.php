<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los UploadId de Cloudflare R2 miden ~343 caracteres; varchar(255) trunca el alta.
        Schema::table('medio_cargas', function (Blueprint $table) {
            $table->string('r2_upload_id', 1024)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('medio_cargas', function (Blueprint $table) {
            $table->string('r2_upload_id')->nullable()->change();
        });
    }
};
