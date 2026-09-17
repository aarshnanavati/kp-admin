<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A "customizable" tiffin (Build Your Own) lets the customer pick individual
     * items pulled dynamically from whatever the admin has in today's other
     * active tiffin plans.
     */
    public function up(): void
    {
        Schema::table('tiffins', function (Blueprint $table) {
            $table->boolean('is_customizable')->default(false)->after('status');
            $table->string('customize_pricing')->default('flat')->after('is_customizable'); // flat | sum
            $table->unsignedInteger('min_items')->nullable()->after('customize_pricing');
            $table->unsignedInteger('max_items')->nullable()->after('min_items');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiffins', function (Blueprint $table) {
            $table->dropColumn(['is_customizable', 'customize_pricing', 'min_items', 'max_items']);
        });
    }
};
