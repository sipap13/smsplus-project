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
        Schema::table('ra_t_audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('ra_t_audit_logs', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('ra_t_audit_logs', 'action')) {
                $table->string('action', 100)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('ra_t_audit_logs', 'target_type')) {
                $table->string('target_type', 50)->nullable()->after('action');
            }
            if (!Schema::hasColumn('ra_t_audit_logs', 'details')) {
                $table->text('details')->nullable()->after('target_type');
            }
            if (!Schema::hasColumn('ra_t_audit_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('details');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ra_t_audit_logs', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'action', 'target_type', 'details', 'ip_address']);
        });
    }
};
