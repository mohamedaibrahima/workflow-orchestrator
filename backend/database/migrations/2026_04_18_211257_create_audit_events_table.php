<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();

            $table->foreignId('request_step_id')
                ->nullable()
                ->constrained('request_step_instances')
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('event_type');
            $table->string('event_key')->nullable()->unique();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index(['request_id', 'event_type']);
            $table->index(['request_step_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};