<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consoles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('console_categories');
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            // nilai: 'available', 'occupied', 'maintenance'
            $table->string('status', 20)->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consoles');
    }
};
