<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ra_t_etl_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('ra_t_etl_jobs', 'page')) {
                $table->string('page', 64)->nullable()->after('job_type');
            }
            // Index ajouté seulement si la colonne vient d'être créée
            if (!Schema::hasColumn('ra_t_etl_jobs', 'page')) {
                $table->index(['page', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ra_t_etl_jobs', function (Blueprint $table) {
            $table->dropIndex(['page', 'status']);
            $table->dropColumn('page');
        });
    }
};
