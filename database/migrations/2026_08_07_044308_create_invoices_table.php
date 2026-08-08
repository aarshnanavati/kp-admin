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
        Schema::create('invoices', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g. INV2001
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->string('order_id')->nullable(); // Can associate with order ID string
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('Pending'); // Paid, Unpaid, Pending
            $table->date('due_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
