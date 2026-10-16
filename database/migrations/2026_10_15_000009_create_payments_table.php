<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_session_id')->nullable()->constrained('rental_sessions');
            $table->foreignId('fnb_order_id')->nullable()->constrained('fnb_orders');
            $table->foreignId('shift_id')->constrained('cashier_shifts');
            // 'cash' atau 'qris'
            $table->string('payment_method', 20);
            $table->decimal('bill_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->timestampTz('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
