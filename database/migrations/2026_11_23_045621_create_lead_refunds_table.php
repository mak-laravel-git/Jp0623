<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_issue_report_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 10);
            $table->unsignedInteger('total_amount');
            $table->unsignedInteger('credit_amount')->default(0);
            $table->unsignedInteger('card_amount')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->string('square_refund_id')->nullable()->index();
            $table->string('square_status', 30)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->string('failure_message', 500)->nullable();
            $table->string('lead_action', 20);
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_refunds');
    }
};
