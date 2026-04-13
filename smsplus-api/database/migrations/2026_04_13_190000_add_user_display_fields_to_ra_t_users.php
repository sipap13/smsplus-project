<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ra_t_users', function (Blueprint $table) {
            if (!Schema::hasColumn('ra_t_users', 'nom')) {
                $table->string('nom', 150)->nullable()->after('password');
            }
            if (!Schema::hasColumn('ra_t_users', 'numero_personnel')) {
                $table->string('numero_personnel', 50)->nullable()->after('nom');
            }
            if (!Schema::hasColumn('ra_t_users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('actif');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ra_t_users', function (Blueprint $table) {
            if (Schema::hasColumn('ra_t_users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
            if (Schema::hasColumn('ra_t_users', 'numero_personnel')) {
                $table->dropColumn('numero_personnel');
            }
            if (Schema::hasColumn('ra_t_users', 'nom')) {
                $table->dropColumn('nom');
            }
        });
    }
};

