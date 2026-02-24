<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_api_refresh_tokens')) {
            return;
        }

        Schema::create('portal_api_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('context', 20); // consumer|admin
            $table->string('token_hash', 64)->unique();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id', 'context'], 'portal_api_refresh_tokens_tokenable_context');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_api_refresh_tokens');
    }
};
