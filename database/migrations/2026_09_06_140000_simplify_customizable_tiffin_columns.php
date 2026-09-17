<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The customizable ("Build Your Own") tiffin is now dead simple: one tiffin
     * flagged is_customizable, no base price, price = sum of the items the
     * customer picks. Drop the pricing-mode / min / max knobs.
     */
    public function up(): void
    {
        Schema::table('tiffins', function (Blueprint $table) {
            $table->dropColumn(['customize_pricing', 'min_items', 'max_items']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tiffins', function (Blueprint $table) {
            $table->string('customize_pricing')->default('flat')->after('is_customizable');
            $table->unsignedInteger('min_items')->nullable()->after('customize_pricing');
            $table->unsignedInteger('max_items')->nullable()->after('min_items');
        });
    }
};
