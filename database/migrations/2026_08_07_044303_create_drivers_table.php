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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('license_no')->nullable();
            $table->string('license_copy_front')->nullable(); // File path or base64
            $table->string('license_copy_back')->nullable(); // File path or base64
            $table->date('license_expiry')->nullable();
            $table->string('vehicle_reg_no')->nullable();
            $table->string('assigned_zip')->nullable(); // Zip/Postcode assignment
            $table->string('area')->nullable(); // Area (compatibility copy of postcode)
            $table->string('status')->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
