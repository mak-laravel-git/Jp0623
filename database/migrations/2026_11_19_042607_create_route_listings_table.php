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
        Schema::create('route_listings', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('about');
            $table->unsignedInteger('locations_count');
            $table->unsignedInteger('machines_count');
            $table->unsignedInteger('annual_revenue');
            $table->unsignedInteger('asking_price');
            $table->boolean('seller_financing')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('listed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_listings');
    }
};
