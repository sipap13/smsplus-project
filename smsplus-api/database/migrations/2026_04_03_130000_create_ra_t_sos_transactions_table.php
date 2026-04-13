<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ra_t_sos_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('msisdn', 20)->index();
            $table->enum('sos_type', ['SOLDE', 'DATA'])->index();
            $table->dateTime('granted_at')->index();
            $table->decimal('credit_amount', 10, 3);
            $table->decimal('fee_amount', 10, 3)->default(0);
            $table->decimal('repaid_amount', 10, 3)->default(0);
            $table->dateTime('repaid_at')->nullable()->index();
            $table->enum('status', ['REMBOURSE', 'PARTIEL', 'IMPAYE'])->index();
            $table->timestamps();

            $table->index(['granted_at', 'sos_type'], 'ra_t_sos_granted_type_idx');
            $table->index(['status', 'granted_at'], 'ra_t_sos_status_granted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ra_t_sos_transactions');
    }
};

