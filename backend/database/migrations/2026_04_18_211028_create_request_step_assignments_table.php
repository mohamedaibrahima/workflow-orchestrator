<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_step_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('request_step_id')->constrained('request_step_instances')->cascadeOnDelete();
            $table->unsignedBigInteger('role_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('status')->default('pending');
            $table->timestamp('acted_at')->nullable();

            $table->timestamps();

            $table->unique(['request_step_id', 'user_id']);
            $table->index(['request_id', 'status']);
            $table->index(['request_step_id', 'status']);

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_step_assignments');
    }
};