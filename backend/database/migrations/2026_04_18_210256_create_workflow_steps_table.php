<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workflow_type_id')
                ->constrained('workflow_types')
                ->cascadeOnDelete();

            $table->string('step_key');
            $table->string('name');

            $table->unsignedBigInteger('role_id');

            $table->unsignedInteger('sequence_order')->default(1);

            $table->enum('execution_type', ['sequential', 'parallel'])->default('sequential');
            $table->string('parallel_group')->nullable();

            $table->enum('approval_mode', ['any', 'all'])->default('any');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->restrictOnDelete();

            $table->unique(['workflow_type_id', 'step_key']);
            $table->index(['workflow_type_id', 'sequence_order']);
            $table->index(['workflow_type_id', 'execution_type']);
            $table->index(['role_id']);
            $table->index(['parallel_group']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};