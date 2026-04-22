<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_step_instances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained('workflow_steps')->cascadeOnDelete();

            $table->string('name');
            $table->string('step_key')->nullable();
            $table->unsignedBigInteger('role_id');
            $table->unsignedInteger('sequence_order');
            $table->string('execution_type')->default('sequential');
            $table->string('approval_mode')->default('any');
            $table->string('parallel_group')->nullable();

            $table->string('status')->default('pending');
            $table->timestamp('acted_at')->nullable();
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->index(['request_id', 'sequence_order']);
            $table->index(['request_id', 'status']);
            $table->index(['parallel_group', 'sequence_order']);

            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->foreign('acted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_step_instances');
    }
};