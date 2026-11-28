<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->nullable();
            $table->string('sid', 34)->nullable();
            $table->text('auth_token')->nullable();
            $table->string('from_number', 20)->nullable();
            $table->string('messaging_service_sid', 34)->nullable();
            $table->unsignedInteger('lead_radius_miles')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_settings');
    }
};
