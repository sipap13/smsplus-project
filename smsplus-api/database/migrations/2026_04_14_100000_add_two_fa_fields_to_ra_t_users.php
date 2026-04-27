<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ra_t_users', function (Blueprint $table) {
            if (! Schema::hasColumn('ra_t_users', 'two_fa_code')) {
                $table->string('two_fa_code', 6)->nullable()->after('actif');
            }
            if (! Schema::hasColumn('ra_t_users', 'two_fa_expires_at')) {
                $table->timestamp('two_fa_expires_at')->nullable()->after('two_fa_code');
            }
            if (! Schema::hasColumn('ra_t_users', 'two_fa_enabled')) {
                $table->boolean('two_fa_enabled')->default(true)->after('two_fa_expires_at');
            }
            if (! Schema::hasColumn('ra_t_users', 'two_fa_method')) {
                $table->enum('two_fa_method', ['email', 'sms', 'both'])->default('email')->after('two_fa_enabled');
            }
            if (! Schema::hasColumn('ra_t_users', 'last_login_ip')) {
                $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ra_t_users', function (Blueprint $table) {
            if (Schema::hasColumn('ra_t_users', 'two_fa_code')) {
                $table->dropColumn('two_fa_code');
            }
            if (Schema::hasColumn('ra_t_users', 'two_fa_expires_at')) {
                $table->dropColumn('two_fa_expires_at');
            }
            if (Schema::hasColumn('ra_t_users', 'two_fa_enabled')) {
                $table->dropColumn('two_fa_enabled');
            }
            if (Schema::hasColumn('ra_t_users', 'two_fa_method')) {
                $table->dropColumn('two_fa_method');
            }
            if (Schema::hasColumn('ra_t_users', 'last_login_ip')) {
                $table->dropColumn('last_login_ip');
            }
        });
    }
};
