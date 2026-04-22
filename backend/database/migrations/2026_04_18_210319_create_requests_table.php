<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('workflow_type_id')->constrained('workflow_types')->cascadeOnDelete();

            $table->unsignedBigInteger('current_step_id')->nullable();
            $table->unsignedBigInteger('rejected_step_id')->nullable();

            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->json('approved_steps')->nullable();

            $table->timestamps();

            $table->index('current_step_id');
            $table->index('rejected_step_id');
            $table->index(['requester_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};