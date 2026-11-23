<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('square_settings', function (Blueprint $table) {
            $table->id();
            $table->string('environment', 20)->nullable();
            $table->string('application_id')->nullable();
            $table->string('location_id')->nullable();
            $table->string('webhook_url')->nullable();
            $table->text('access_token')->nullable();
            $table->text('webhook_signature_key')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('square_settings');
    }
};
