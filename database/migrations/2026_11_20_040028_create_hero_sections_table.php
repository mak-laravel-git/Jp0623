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
        Schema::create('hero_sections', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow');
            $table->string('title_before');
            $table->string('title_accent');
            $table->text('lede');
            $table->json('bullets');
            $table->string('cta_label');
            $table->string('cta_href');
            $table->string('trust_stars');
            $table->string('trust_text');
            $table->string('trust_reviews_label');
            $table->string('trust_reviews_href');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hero_sections');
    }
};
