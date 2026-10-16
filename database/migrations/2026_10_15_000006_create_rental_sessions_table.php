<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 40)->unique();
            $table->foreignId('console_id')->constrained('consoles');
            $table->foreignId('shift_id')->constrained('cashier_shifts');
            $table->foreignId('user_id')->constrained('users');
            $table->string('customer_name', 100)->nullable();

            // 'package' atau 'open'
            $table->string('billing_mode', 20);
            $table->foreignId('package_id')->nullable()->constrained('pricing_packages');

            // waktu (TIMESTAMPTZ)
            $table->timestampTz('start_time');
            $table->timestampTz('planned_end_time')->nullable();
            $table->timestampTz('actual_end_time')->nullable();
            $table->integer('total_minutes')->default(0);

            // tagihan
            $table->decimal('rental_amount', 12, 2)->default(0);
            $table->decimal('fnb_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            // status
            $table->string('payment_status', 20)->default('unpaid'); // unpaid / paid / void
            $table->string('session_status', 20)->default('running'); // running / completed / cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_sessions');
    }
};
