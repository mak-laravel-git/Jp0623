<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->unsignedInteger('credits_applied')->default(0)->after('price');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('credit_balance')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('lead_purchases', function (Blueprint $table) {
            $table->dropColumn('credits_applied');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('credit_balance');
        });
    }
};
