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
        Schema::create('tiffins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // Lunch, Dinner, Both
            $table->decimal('price', 10, 2);
            $table->text('items'); // List of items
            $table->text('description')->nullable();
            $table->integer('prep_time')->default(30);
            $table->string('status')->default('Active');
            $table->string('image')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tiffins');
    }
};
