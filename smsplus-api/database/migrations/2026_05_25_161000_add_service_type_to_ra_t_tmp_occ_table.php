<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ra_t_tmp_occ', function (Blueprint $table) {
            $table->string('service_type', 50)->nullable()->after('subscriber_type');
        });
    }

    public function down(): void
    {
        Schema::table('ra_t_tmp_occ', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
