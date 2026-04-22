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
        Schema::create('dead_letter_jobs', function (Blueprint $table) {
            $table->id();

            $table->string('queue')->nullable();
            $table->string('job_class')->nullable();

            $table->unsignedBigInteger('related_request_id')->nullable();
            $table->unsignedBigInteger('related_step_instance_id')->nullable();

            $table->json('payload')->nullable();

            $table->text('exception_message')->nullable();
            $table->longText('exception_trace')->nullable();

            $table->unsignedInteger('attempts')->default(0);

            $table->enum('status', [
                'failed',
                'retried',
                'resolved',
            ])->default('failed');

            $table->timestamp('failed_at')->useCurrent();
            $table->timestamp('retried_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['queue']);
            $table->index(['job_class']);
            $table->index(['related_request_id']);
            $table->index(['related_step_instance_id']);
            $table->index(['status']);
            $table->index(['failed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dead_letter_jobs');
    }
};