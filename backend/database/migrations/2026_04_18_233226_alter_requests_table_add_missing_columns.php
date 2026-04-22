<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (!Schema::hasColumn('requests', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }

            if (!Schema::hasColumn('requests', 'workflow_type_id')) {
                $table->foreignId('workflow_type_id')->nullable()->after('user_id')->constrained('workflow_types')->nullOnDelete();
            }

            if (!Schema::hasColumn('requests', 'status')) {
                $table->string('status')->default('pending')->after('workflow_type_id');
            }

            if (!Schema::hasColumn('requests', 'payload')) {
                $table->json('payload')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (Schema::hasColumn('requests', 'payload')) {
                $table->dropColumn('payload');
            }

            if (Schema::hasColumn('requests', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('requests', 'workflow_type_id')) {
                $table->dropConstrainedForeignId('workflow_type_id');
            }

            if (Schema::hasColumn('requests', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};