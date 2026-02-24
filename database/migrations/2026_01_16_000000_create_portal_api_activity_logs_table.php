<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_api_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action');

            $table->string('actor_type')->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable()->index();

            $table->string('subject_type')->nullable()->index();
            $table->string('subject_id')->nullable()->index();
            $table->string('subject_label')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 1024)->nullable();

            $table->json('properties')->nullable();

            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_api_activity_logs');
    }
};

