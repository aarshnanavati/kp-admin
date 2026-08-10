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
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g. KP1001
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('customer'); // For compatibility and quick rendering
            $table->foreignId('tiffin_id')->nullable()->constrained('tiffins')->onDelete('set null');
            $table->string('tiffin'); // For compatibility and quick rendering
            $table->string('area'); // Zipcode or area
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->onDelete('set null');
            $table->string('driver')->default('Unassigned'); // For compatibility and quick rendering
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('Pending');
            $table->date('date');
            $table->text('add_ons')->nullable(); // JSON list of add-ons
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
