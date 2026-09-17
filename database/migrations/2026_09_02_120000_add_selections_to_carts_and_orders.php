<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a JSON "selections" column so a cart line / order can remember which
     * alternative was picked for each "Or" choice slot of a tiffin plan
     * (e.g. Bhakhari vs Methi Thepla), plus the resolved unit price.
     */
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->text('selections')->nullable()->after('quantity');
        });

        Schema::table('guest_carts', function (Blueprint $table) {
            $table->text('selections')->nullable()->after('quantity');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->text('selections')->nullable()->after('add_ons');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('selections');
        });

        Schema::table('guest_carts', function (Blueprint $table) {
            $table->dropColumn('selections');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('selections');
        });
    }
};
