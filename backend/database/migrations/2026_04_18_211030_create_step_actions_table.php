<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('step_actions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('request_step_id')->constrained('request_step_instances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('action');
            $table->text('comment')->nullable();

            $table->string('idempotency_key')->nullable()->unique();
            $table->boolean('is_effective')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'action']);
            $table->index(['request_step_id', 'action']);
            $table->index(['user_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('step_actions');
    }
};