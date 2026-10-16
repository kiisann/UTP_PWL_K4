<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fnb_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();
            // NULL = pembelian langsung (walk-in)
            $table->foreignId('rental_session_id')->nullable()->constrained('rental_sessions');
            $table->foreignId('shift_id')->constrained('cashier_shifts');
            $table->string('customer_name', 100)->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('order_status', 20)->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fnb_orders');
    }
};
