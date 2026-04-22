<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (!Schema::hasColumn('requests', 'current_step_id')) {
                $table->unsignedBigInteger('current_step_id')->nullable()->after('workflow_type_id');
            }

            if (!Schema::hasColumn('requests', 'approved_steps')) {
                $table->json('approved_steps')->nullable()->after('payload');
            }

            if (!Schema::hasColumn('requests', 'rejected_step_id')) {
                $table->unsignedBigInteger('rejected_step_id')->nullable()->after('approved_steps');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (Schema::hasColumn('requests', 'rejected_step_id')) {
                $table->dropColumn('rejected_step_id');
            }

            if (Schema::hasColumn('requests', 'approved_steps')) {
                $table->dropColumn('approved_steps');
            }

            if (Schema::hasColumn('requests', 'current_step_id')) {
                $table->dropColumn('current_step_id');
            }
        });
    }
};