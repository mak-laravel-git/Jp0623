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
        Schema::create('lead_issue_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_purchase_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->text('reason');
            $table->string('status', 20)->default('open')->index();
            $table->text('admin_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->string('lead_reference', 20);
            $table->string('lead_name')->nullable();
            $table->string('lead_type')->nullable();
            $table->string('lead_city')->nullable();
            $table->unsignedInteger('price');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_issue_reports');
    }
};
