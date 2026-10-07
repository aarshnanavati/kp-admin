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
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('status');
            }
            if (!Schema::hasColumn('customers', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('is_verified');
            }
        });

        // Set existing customers as verified so they can continue to log in
        \App\Models\Customer::whereNull('is_verified')->orWhere('is_verified', false)->update([
            'is_verified' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }
            if (Schema::hasColumn('customers', 'is_verified')) {
                $table->dropColumn('is_verified');
            }
        });
    }
};
