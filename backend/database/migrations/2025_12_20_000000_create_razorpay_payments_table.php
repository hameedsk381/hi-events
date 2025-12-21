<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('razorpay_payments')) {
            Schema::create('razorpay_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders');
                $table->string('razorpay_order_id')->unique();
                $table->string('razorpay_payment_id')->nullable();
                $table->string('razorpay_signature')->nullable();
                $table->integer('amount')->nullable();
                $table->string('currency', 3)->nullable();
                $table->string('status')->nullable();
                $table->string('method')->nullable();
                $table->json('error_details')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::dropIfExists('stripe_payments');
        Schema::dropIfExists('account_stripe_platforms');
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_payments');
    }
};
