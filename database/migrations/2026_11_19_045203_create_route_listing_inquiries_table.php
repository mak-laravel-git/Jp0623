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
        Schema::create('route_listing_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 50)->nullable();
            $table->string('listing_reference', 20);
            $table->string('listing_city');
            $table->unsignedInteger('listing_asking_price');
            $table->text('message');
            $table->string('status')->default('pending')->index();
            $table->timestamps();

            $table->index(['user_id', 'route_listing_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_listing_inquiries');
    }
};
