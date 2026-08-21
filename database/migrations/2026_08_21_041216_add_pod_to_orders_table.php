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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('proof_of_delivery_photo')->nullable()->after('add_ons');
            $table->string('proof_of_delivery_signature')->nullable()->after('proof_of_delivery_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['proof_of_delivery_photo', 'proof_of_delivery_signature']);
        });
    }
};
