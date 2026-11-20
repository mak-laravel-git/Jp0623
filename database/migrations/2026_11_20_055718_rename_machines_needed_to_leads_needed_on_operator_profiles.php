<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signup now asks "How many quality leads are you looking for?", so the column is renamed to match.
     * Existing answers keep their values.
     */
    public function up(): void
    {
        Schema::table('operator_profiles', function (Blueprint $table) {
            $table->renameColumn('machines_needed', 'leads_needed');
        });
    }

    public function down(): void
    {
        Schema::table('operator_profiles', function (Blueprint $table) {
            $table->renameColumn('leads_needed', 'machines_needed');
        });
    }
};
