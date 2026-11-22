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
        Schema::table('operator_memberships', function (Blueprint $table) {
            $table->boolean('pays_default_price')->default(false)->after('monthly_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operator_memberships', function (Blueprint $table) {
            $table->dropColumn('pays_default_price');
        });
    }
};
