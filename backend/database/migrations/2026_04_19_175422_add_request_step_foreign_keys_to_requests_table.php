<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->foreign('current_step_id')
                ->references('id')
                ->on('request_step_instances')
                ->nullOnDelete();

            $table->foreign('rejected_step_id')
                ->references('id')
                ->on('request_step_instances')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropForeign(['current_step_id']);
            $table->dropForeign(['rejected_step_id']);
        });
    }
};