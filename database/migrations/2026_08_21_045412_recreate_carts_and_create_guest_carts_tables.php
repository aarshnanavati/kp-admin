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
        // Drop existing carts table if it exists
        Schema::dropIfExists('carts');

        // Recreate carts table for logged-in customers (non-nullable customer_id)
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('tiffin_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            $table->foreign('tiffin_id')->references('id')->on('tiffins')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
        });

        // Create guest_carts table for guest users
        Schema::create('guest_carts', function (Blueprint $table) {
            $table->id();
            $table->string('temp_user_id');
            $table->unsignedBigInteger('tiffin_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->foreign('tiffin_id')->references('id')->on('tiffins')->onDelete('cascade');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_carts');
        Schema::dropIfExists('carts');
    }
};
